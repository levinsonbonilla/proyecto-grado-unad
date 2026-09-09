<?php

namespace App\Tests\Unit\Handler\UseCase\Messages;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Handler\UseCase\Messages\QuickComposeMessageUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Messages\MessageNotificationInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\UsersRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class QuickComposeMessageUseCaseTest extends TestCase
{
    private RequestStack&MockObject $requestStack;
    private Security&MockObject $security;
    private UsersRepository&MockObject $usersRepository;
    private GetDomainDataInterface&MockObject $getDomainData;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private S3ManagerInterface&MockObject $s3Manager;
    private ParameterBagInterface&MockObject $parameters;
    private LogInterface&MockObject $log;
    private TranslatorInterface&MockObject $translator;
    private MessageNotificationInterface&MockObject $messageNotification;

    protected function setUp(): void
    {
        $this->requestStack          = $this->createMock(RequestStack::class);
        $this->security              = $this->createMock(Security::class);
        $this->usersRepository       = $this->createMock(UsersRepository::class);
        $this->getDomainData         = $this->createMock(GetDomainDataInterface::class);
        $this->entityManager         = $this->createMock(CustomeEntityManagerInterface::class);
        $this->s3Manager             = $this->createMock(S3ManagerInterface::class);
        $this->parameters            = $this->createMock(ParameterBagInterface::class);
        $this->log                   = $this->createMock(LogInterface::class);
        $this->translator            = $this->createMock(TranslatorInterface::class);
        $this->messageNotification   = $this->createMock(MessageNotificationInterface::class);

        $domain = $this->createMock(Domains::class);
        $this->getDomainData->method('getDomainCache')->willReturn($domain);
        $this->parameters->method('get')->with('upload_messages')->willReturn('uploads/messages/');
        $this->translator->method('trans')->willReturn('Mensaje enviado');
    }

    private function makeUseCase(): QuickComposeMessageUseCase
    {
        return new QuickComposeMessageUseCase(
            $this->requestStack,
            $this->security,
            $this->usersRepository,
            $this->getDomainData,
            $this->entityManager,
            $this->s3Manager,
            $this->parameters,
            $this->log,
            $this->translator,
            $this->messageNotification,
        );
    }

    public function testHandlerReturnsErrorWhenMessageIsEmpty(): void
    {
        $request = Request::create('/quick-compose', 'POST', ['message' => '']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $result = $this->makeUseCase()->handler();

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['message']);
    }

    public function testHandlerAutoSelectsWhenOneSuperAdmin(): void
    {
        $request = Request::create('/quick-compose', 'POST', ['message' => 'Necesito ayuda']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $currentUser = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($currentUser);
        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(false);

        $superAdmin = $this->createMock(Users::class);
        $this->usersRepository->method('findSuperAdminsByDomain')->willReturn([$superAdmin]);

        $this->entityManager->expects($this->atLeastOnce())->method('add');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->makeUseCase()->handler();

        $this->assertTrue($result['success']);
    }

    public function testHandlerReturnsErrorWhenNoRecipientsFound(): void
    {
        $request = Request::create('/quick-compose', 'POST', ['message' => 'Mensaje de prueba']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $currentUser = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($currentUser);
        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(false);

        $this->usersRepository->method('findSuperAdminsByDomain')->willReturn([]);

        $result = $this->makeUseCase()->handler();

        $this->assertFalse($result['success']);
    }
}
