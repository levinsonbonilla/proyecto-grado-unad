<?php

namespace App\Tests\Unit\Handler\UseCase\Messages;

use App\Entity\Users\HelpMessages;
use App\Entity\Users\Users;
use App\Form\Messages\ReplyType;
use App\Handler\UseCase\Messages\ReplyMessageUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReplyMessageUseCaseTest extends TestCase
{
    private FormFactoryInterface&MockObject $formFactory;
    private FormInterface&MockObject $form;
    private RequestStack&MockObject $requestStack;
    private Request&MockObject $request;
    private Security&MockObject $security;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private S3ManagerInterface&MockObject $s3Manager;
    private ParameterBagInterface&MockObject $parameters;
    private LogInterface&MockObject $log;
    private TranslatorInterface&MockObject $translator;
    private ReplyMessageUseCase $useCase;

    protected function setUp(): void
    {
        $this->form = $this->createMock(FormInterface::class);
        $this->form->method('handleRequest')->willReturnSelf();
        $this->form->method('getName')->willReturn('reply');

        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->formFactory->method('create')->willReturn($this->form);

        $this->request       = $this->createMock(Request::class);
        $this->request->files = new FileBag([]);

        $this->requestStack = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($this->request);

        $this->security      = $this->createMock(Security::class);
        $this->entityManager = $this->createMock(CustomeEntityManagerInterface::class);
        $this->s3Manager     = $this->createMock(S3ManagerInterface::class);
        $this->parameters    = $this->createMock(ParameterBagInterface::class);
        $this->log           = $this->createMock(LogInterface::class);
        $this->translator    = $this->createMock(TranslatorInterface::class);

        $this->translator->method('trans')->willReturn('Respuesta enviada');

        $this->useCase = new ReplyMessageUseCase(
            $this->requestStack,
            $this->formFactory,
            $this->log,
            $this->translator,
            $this->security,
            $this->entityManager,
            $this->s3Manager,
            $this->parameters,
        );
    }

    public function testHandlerWithNoSubmissionDoesNotProcess(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $parent = $this->createMock(HelpMessages::class);

        $result = $this->useCase->handler($parent, ReplyType::class);

        $this->assertFalse($result->isProcess());
    }

    public function testHandlerWithValidFormCreatesReply(): void
    {
        $currentUserUuid = Uuid::fromString('550e8400-e29b-41d4-a716-446655440001');
        $fromUserUuid    = Uuid::fromString('550e8400-e29b-41d4-a716-446655440002');
        $toUserUuid      = Uuid::fromString('550e8400-e29b-41d4-a716-446655440003');

        $currentUser = $this->createMock(Users::class);
        $currentUser->method('getId')->willReturn($currentUserUuid);

        $fromUser = $this->createMock(Users::class);
        $fromUser->method('getId')->willReturn($fromUserUuid);

        $toUser = $this->createMock(Users::class);
        $toUser->method('getId')->willReturn($toUserUuid);

        $parent = $this->createMock(HelpMessages::class);
        $parent->method('getFromUser')->willReturn($fromUser);
        $parent->method('getToUser')->willReturn($toUser);

        $this->security->method('getUser')->willReturn($currentUser);

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('reply', [])->willReturn([
            'message' => 'Esta es mi respuesta',
        ]);

        $this->entityManager->expects($this->atLeastOnce())->method('add');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->useCase->handler($parent, ReplyType::class);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }
}
