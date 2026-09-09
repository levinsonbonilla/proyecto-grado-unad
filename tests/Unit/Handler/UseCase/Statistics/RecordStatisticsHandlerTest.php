<?php

namespace App\Tests\Unit\Handler\UseCase\Statistics;

use App\Entity\Tenants\Domains\Domains;
use App\Handler\UseCase\Statistics\RecordStatisticsHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Service\Statistics\UserAgentParserInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Message\Statistics\RecordStatisticsMessage;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\Repository\Users\UsersRepository;
use App\Service\Statistics\UserAgentData;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RecordStatisticsHandlerTest extends TestCase
{
    private DomainsRepository&MockObject             $domainsRepository;
    private UserAgentParserInterface&MockObject      $uaParser;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private LogInterface&MockObject                  $log;
    private UsersRepository&MockObject               $usersRepository;
    private RecordStatisticsHandler                  $handler;

    protected function setUp(): void
    {
        $this->domainsRepository = $this->createMock(DomainsRepository::class);
        $this->uaParser          = $this->createMock(UserAgentParserInterface::class);
        $this->entityManager     = $this->createMock(CustomeEntityManagerInterface::class);
        $this->log               = $this->createMock(LogInterface::class);
        $this->usersRepository   = $this->createMock(UsersRepository::class);

        $this->handler = new RecordStatisticsHandler(
            $this->domainsRepository,
            $this->uaParser,
            $this->entityManager,
            $this->log,
            $this->usersRepository,
        );
    }

    private function makeMessage(string $ua = 'Mozilla/5.0 Chrome'): RecordStatisticsMessage
    {
        return new RecordStatisticsMessage(
            domainId:       '550e8400-e29b-41d4-a716-446655440000',
            ip:             '192.168.1.1',
            userAgent:      $ua,
            lang:           'es',
            page:           '/es/shop',
            referrer:       'https://google.com',
            utmSource:      'google',
            utmMedium:      'cpc',
            utmCampaign:    'test',
            sessionId:      'session-123',
            country:        'Colombia',
            region:         'Cundinamarca',
            city:           'Bogotá',
            countryIsoCode: 'CO',
        );
    }

    public function testHandlerSkipsWhenDomainNotFound(): void
    {
        $this->domainsRepository->method('find')->willReturn(null);
        $this->entityManager->expects($this->never())->method('add');

        $this->handler->__invoke($this->makeMessage());
    }

    public function testHandlerSkipsBots(): void
    {
        $domain = $this->createMock(Domains::class);
        $this->domainsRepository->method('find')->willReturn($domain);
        $this->uaParser->method('parse')->willReturn(new UserAgentData(device: 'bot', browser: 'bot', os: 'bot'));

        $this->entityManager->expects($this->never())->method('add');

        $this->handler->__invoke($this->makeMessage('Googlebot/2.1'));
    }

    public function testHandlerPersistsStatisticsForValidRequest(): void
    {
        $domain = $this->createMock(Domains::class);
        $this->domainsRepository->method('find')->willReturn($domain);
        $this->uaParser->method('parse')->willReturn(new UserAgentData(device: 'desktop', browser: 'Chrome', os: 'Windows'));

        $this->entityManager->expects($this->once())->method('add');

        $this->handler->__invoke($this->makeMessage());
    }

    public function testHandlerExtractsReferrerDomain(): void
    {
        $domain = $this->createMock(Domains::class);
        $this->domainsRepository->method('find')->willReturn($domain);
        $this->uaParser->method('parse')->willReturn(new UserAgentData(device: 'desktop', browser: 'Firefox', os: 'Linux'));

        $this->entityManager->expects($this->once())->method('add');

        $message = new RecordStatisticsMessage(
            domainId: '550e8400-e29b-41d4-a716-446655440000',
            ip: '1.2.3.4', userAgent: 'Firefox', lang: 'es',
            page: '/es/home', referrer: 'https://facebook.com/page',
            utmSource: null, utmMedium: null, utmCampaign: null,
            sessionId: null, country: null, region: null, city: null,
        );

        $this->handler->__invoke($message);
    }

    public function testHandlerResolvesUserWhenUserIdPresentAndFound(): void
    {
        $domain = $this->createMock(Domains::class);
        $this->domainsRepository->method('find')->willReturn($domain);
        $this->uaParser->method('parse')->willReturn(new UserAgentData(device: 'desktop', browser: 'Chrome', os: 'Windows'));

        $user = $this->createMock(\App\Entity\Users\Users::class);
        $this->usersRepository->expects($this->once())->method('find')->willReturn($user);

        $captured = null;
        $this->entityManager->method('add')->willReturnCallback(function ($entity) use (&$captured) {
            $captured = $entity;
        });

        $message = new RecordStatisticsMessage(
            domainId: '550e8400-e29b-41d4-a716-446655440000',
            ip: '1.2.3.4', userAgent: 'Chrome', lang: 'es', page: '/es/shop',
            referrer: null, utmSource: null, utmMedium: null, utmCampaign: null,
            sessionId: 'session-1', country: null, region: null, city: null,
            userId: '660e8400-e29b-41d4-a716-446655440001',
        );

        $this->handler->__invoke($message);

        $this->assertNotNull($captured);
        $this->assertSame($user, $captured->getUser());
    }

    public function testHandlerKeepsRecordWhenUserIdPresentButNotFound(): void
    {
        $domain = $this->createMock(Domains::class);
        $this->domainsRepository->method('find')->willReturn($domain);
        $this->uaParser->method('parse')->willReturn(new UserAgentData(device: 'desktop', browser: 'Chrome', os: 'Windows'));
        $this->usersRepository->method('find')->willReturn(null);

        $this->entityManager->expects($this->once())->method('add');

        $message = new RecordStatisticsMessage(
            domainId: '550e8400-e29b-41d4-a716-446655440000',
            ip: '1.2.3.4', userAgent: 'Chrome', lang: 'es', page: '/es/shop',
            referrer: null, utmSource: null, utmMedium: null, utmCampaign: null,
            sessionId: 'session-1', country: null, region: null, city: null,
            userId: '660e8400-e29b-41d4-a716-446655440001',
        );

        $this->handler->__invoke($message);
    }

    public function testHandlerNeverCallsUsersRepositoryWhenUserIdIsNull(): void
    {
        $domain = $this->createMock(Domains::class);
        $this->domainsRepository->method('find')->willReturn($domain);
        $this->uaParser->method('parse')->willReturn(new UserAgentData(device: 'desktop', browser: 'Chrome', os: 'Windows'));

        $this->usersRepository->expects($this->never())->method('find');

        $this->handler->__invoke($this->makeMessage());
    }

    public function testHandlerLogsExceptionAndDoesNotThrow(): void
    {
        $this->domainsRepository->method('find')->willThrowException(new \RuntimeException('DB error'));
        $this->log->expects($this->once())->method('handler');
        $this->entityManager->expects($this->never())->method('add');

        $this->handler->__invoke($this->makeMessage());
    }
}
