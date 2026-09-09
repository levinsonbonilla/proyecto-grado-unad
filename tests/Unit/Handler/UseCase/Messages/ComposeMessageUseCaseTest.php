<?php

namespace App\Tests\Unit\Handler\UseCase\Messages;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\HelpMessages;
use App\Entity\Users\Users;
use App\Form\Messages\ComposeType;
use App\Handler\UseCase\Messages\ComposeMessageUseCase;
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
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class ComposeMessageUseCaseTest extends TestCase
{
    private FormFactoryInterface&MockObject $formFactory;
    private FormInterface&MockObject $form;
    private RequestStack&MockObject $requestStack;
    private Request&MockObject $request;
    private Security&MockObject $security;
    private UsersRepository&MockObject $usersRepository;
    private GetDomainDataInterface&MockObject $getDomainData;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private S3ManagerInterface&MockObject $s3Manager;
    private ParameterBagInterface&MockObject $parameters;
    private LogInterface&MockObject $log;
    private TranslatorInterface&MockObject $translator;
    private MessageNotificationInterface&MockObject $messageNotification;
    private ComposeMessageUseCase $useCase;

    protected function setUp(): void
    {
        $this->form = $this->createMock(FormInterface::class);
        $this->form->method('handleRequest')->willReturnSelf();
        $this->form->method('getName')->willReturn('compose');

        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->formFactory->method('create')->willReturn($this->form);

        $this->request       = $this->createMock(Request::class);
        $this->request->files = new FileBag([]);

        $this->requestStack = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($this->request);

        $this->security        = $this->createMock(Security::class);
        $this->usersRepository = $this->createMock(UsersRepository::class);
        $this->getDomainData   = $this->createMock(GetDomainDataInterface::class);
        $this->entityManager   = $this->createMock(CustomeEntityManagerInterface::class);
        $this->s3Manager       = $this->createMock(S3ManagerInterface::class);
        $this->parameters      = $this->createMock(ParameterBagInterface::class);
        $this->log                 = $this->createMock(LogInterface::class);
        $this->translator          = $this->createMock(TranslatorInterface::class);
        $this->messageNotification = $this->createMock(MessageNotificationInterface::class);

        $currentUser = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($currentUser);

        $domain = $this->createMock(Domains::class);
        $this->getDomainData->method('getDomainCache')->willReturn($domain);
        $this->translator->method('trans')->willReturn('Creado exitosamente');

        $this->useCase = new ComposeMessageUseCase(
            $this->requestStack,
            $this->formFactory,
            $this->translator,
            $this->log,
            $this->security,
            $this->usersRepository,
            $this->getDomainData,
            $this->entityManager,
            $this->s3Manager,
            $this->parameters,
            $this->messageNotification,
        );
    }

    public function testHandlerWithNoSubmissionDoesNotProcess(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(false);
        $this->usersRepository->method('findSuperAdminsByDomain')->willReturn([]);

        $result = $this->useCase->handler(ComposeType::class);

        $this->assertFalse($result->isProcess());
    }

    public function testHandlerSuperAdminCreatesMessagesForMultipleRecipients(): void
    {
        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(true);

        $recipient1 = $this->createMock(Users::class);
        $recipient2 = $this->createMock(Users::class);
        $this->usersRepository->method('findActiveByDomain')->willReturn([$recipient1, $recipient2]);
        $this->usersRepository->method('find')
            ->willReturnOnConsecutiveCalls($recipient1, $recipient2);

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('compose', [])->willReturn([
            'message' => 'Mensaje de prueba',
            'toUsers' => ['uuid-1', 'uuid-2'],
        ]);

        $this->entityManager->expects($this->atLeastOnce())->method('add');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->useCase->handler(ComposeType::class);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerAdminAutoSelectsUniqueSuperAdmin(): void
    {
        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(false);

        $superAdmin = $this->createMock(Users::class);
        $this->usersRepository->method('findSuperAdminsByDomain')->willReturn([$superAdmin]);

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('compose', [])->willReturn([
            'message' => 'Necesito ayuda',
        ]);

        $this->entityManager->expects($this->atLeastOnce())->method('add');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->useCase->handler(ComposeType::class);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerPersistsSubjectWhenProvided(): void
    {
        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(false);

        $superAdmin = $this->createMock(Users::class);
        $this->usersRepository->method('findSuperAdminsByDomain')->willReturn([$superAdmin]);

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('compose', [])->willReturn([
            'message' => 'Necesito ayuda',
            'subject' => 'Problema con mi pedido',
        ]);

        $persisted = null;
        $this->entityManager->method('add')->willReturnCallback(function ($entity) use (&$persisted) {
            if ($entity instanceof HelpMessages) {
                $persisted = $entity;
            }
        });

        $this->useCase->handler(ComposeType::class);

        $this->assertNotNull($persisted);
        $this->assertSame('Problema con mi pedido', $persisted->getSubject());
    }

    public function testHandlerLeavesSubjectNullWhenNotProvided(): void
    {
        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(false);

        $superAdmin = $this->createMock(Users::class);
        $this->usersRepository->method('findSuperAdminsByDomain')->willReturn([$superAdmin]);

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('compose', [])->willReturn([
            'message' => 'Necesito ayuda',
        ]);

        $persisted = null;
        $this->entityManager->method('add')->willReturnCallback(function ($entity) use (&$persisted) {
            if ($entity instanceof HelpMessages) {
                $persisted = $entity;
            }
        });

        $this->useCase->handler(ComposeType::class);

        $this->assertNotNull($persisted);
        $this->assertNull($persisted->getSubject());
    }

    public function testHandlerReturnsErrorWhenNoRecipientsSelected(): void
    {
        $this->security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(true);
        $this->usersRepository->method('findActiveByDomain')->willReturn([]);

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('compose', [])->willReturn([
            'message' => 'Mensaje',
            'toUsers' => [],
        ]);

        $result = $this->useCase->handler(ComposeType::class);

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }
}
