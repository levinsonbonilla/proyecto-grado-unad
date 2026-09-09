<?php

namespace App\Tests\Unit\Handler\UseCase\Statistics;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Handler\UseCase\Statistics\RecordStatisticsEventHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Message\Statistics\RecordStatisticsEventMessage;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\Repository\Users\UsersRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RecordStatisticsEventHandlerTest extends TestCase
{
    private DomainsRepository&MockObject             $domainsRepository;
    private UsersRepository&MockObject                $usersRepository;
    private CustomeEntityManagerInterface&MockObject  $entityManager;
    private LogInterface&MockObject                   $log;
    private RecordStatisticsEventHandler               $handler;

    protected function setUp(): void
    {
        $this->domainsRepository = $this->createMock(DomainsRepository::class);
        $this->usersRepository   = $this->createMock(UsersRepository::class);
        $this->entityManager     = $this->createMock(CustomeEntityManagerInterface::class);
        $this->log               = $this->createMock(LogInterface::class);

        $this->handler = new RecordStatisticsEventHandler(
            $this->domainsRepository,
            $this->usersRepository,
            $this->entityManager,
            $this->log,
        );
    }

    private function makeMessage(?string $userId = null): RecordStatisticsEventMessage
    {
        return new RecordStatisticsEventMessage(
            domainId: '550e8400-e29b-41d4-a716-446655440000',
            eventName: 'add_to_cart',
            eventTarget: 'product-123',
            page: '/es/product/123',
            metadata: ['price' => 5000],
            sessionId: 'session-123',
            userId: $userId,
        );
    }

    public function testHandlerSkipsWhenDomainNotFound(): void
    {
        $this->domainsRepository->method('find')->willReturn(null);
        $this->entityManager->expects($this->never())->method('add');

        $this->handler->__invoke($this->makeMessage());
    }

    public function testHandlerPersistsEventForValidMessage(): void
    {
        $domain = $this->createMock(Domains::class);
        $this->domainsRepository->method('find')->willReturn($domain);
        $this->usersRepository->expects($this->never())->method('find');

        $captured = null;
        $this->entityManager->method('add')->willReturnCallback(function ($entity) use (&$captured) {
            $captured = $entity;
        });

        $this->handler->__invoke($this->makeMessage());

        $this->assertNotNull($captured);
        $this->assertSame('add_to_cart', $captured->getEventName());
        $this->assertSame('product-123', $captured->getEventTarget());
        $this->assertSame(['price' => 5000], $captured->getMetadata());
    }

    public function testHandlerResolvesUserWhenUserIdPresent(): void
    {
        $domain = $this->createMock(Domains::class);
        $this->domainsRepository->method('find')->willReturn($domain);
        $user = $this->createMock(Users::class);
        $this->usersRepository->expects($this->once())->method('find')->willReturn($user);

        $captured = null;
        $this->entityManager->method('add')->willReturnCallback(function ($entity) use (&$captured) {
            $captured = $entity;
        });

        $this->handler->__invoke($this->makeMessage('660e8400-e29b-41d4-a716-446655440001'));

        $this->assertSame($user, $captured->getUser());
    }

    public function testHandlerLogsExceptionAndDoesNotThrow(): void
    {
        $this->domainsRepository->method('find')->willThrowException(new \RuntimeException('DB error'));
        $this->log->expects($this->once())->method('handler');
        $this->entityManager->expects($this->never())->method('add');

        $this->handler->__invoke($this->makeMessage());
    }
}
