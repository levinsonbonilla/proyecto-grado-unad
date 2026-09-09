<?php

namespace App\Tests\Unit\Handler\UseCase\Messages;

use App\Entity\Users\HelpMessages;
use App\Entity\Users\Users;
use App\Handler\UseCase\Messages\ViewThreadUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\HelpMessageImagesRepository;
use App\Repository\Users\HelpMessagesRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

class ViewThreadUseCaseTest extends TestCase
{
    private Security&MockObject $security;
    private HelpMessagesRepository&MockObject $repository;
    private HelpMessageImagesRepository&MockObject $imagesRepository;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private LogInterface&MockObject $log;
    private ViewThreadUseCase $useCase;

    protected function setUp(): void
    {
        $this->security         = $this->createMock(Security::class);
        $this->repository       = $this->createMock(HelpMessagesRepository::class);
        $this->imagesRepository = $this->createMock(HelpMessageImagesRepository::class);
        $this->entityManager    = $this->createMock(CustomeEntityManagerInterface::class);
        $this->log              = $this->createMock(LogInterface::class);

        $this->imagesRepository->method('getGroupedByMessageIds')->willReturn([]);

        $this->useCase = new ViewThreadUseCase(
            $this->security,
            $this->repository,
            $this->imagesRepository,
            $this->entityManager,
            $this->log,
        );
    }

    public function testHandlerMarksAsReadWhenCurrentUserIsRecipient(): void
    {
        $sharedUuid  = Uuid::fromString('550e8400-e29b-41d4-a716-446655440001');

        $currentUser = $this->createMock(Users::class);
        $currentUser->method('getId')->willReturn($sharedUuid);

        $toUser = $this->createMock(Users::class);
        $toUser->method('getId')->willReturn($sharedUuid);

        $message = $this->createMock(HelpMessages::class);
        $message->method('isRead')->willReturn(false);
        $message->method('getToUser')->willReturn($toUser);
        $message->expects($this->once())->method('markAsRead');

        $this->security->method('getUser')->willReturn($currentUser);
        $this->repository->method('getThread')->willReturn([]);
        $this->entityManager->expects($this->once())->method('add');

        $result = $this->useCase->handler($message);

        $this->assertTrue($result['success']);
    }

    public function testHandlerDoesNotMarkReadWhenCurrentUserIsSender(): void
    {
        $currentUser = $this->createMock(Users::class);
        $currentUser->method('getId')->willReturn(Uuid::fromString('550e8400-e29b-41d4-a716-446655440001'));

        $toUser = $this->createMock(Users::class);
        $toUser->method('getId')->willReturn(Uuid::fromString('550e8400-e29b-41d4-a716-446655440002'));

        $message = $this->createMock(HelpMessages::class);
        $message->method('isRead')->willReturn(false);
        $message->method('getToUser')->willReturn($toUser);
        $message->expects($this->never())->method('markAsRead');

        $this->security->method('getUser')->willReturn($currentUser);
        $this->repository->method('getThread')->willReturn([]);
        $this->entityManager->expects($this->never())->method('add');

        $result = $this->useCase->handler($message);

        $this->assertTrue($result['success']);
    }

    public function testHandlerDoesNotMarkAlreadyReadMessage(): void
    {
        $sharedUuid = Uuid::fromString('550e8400-e29b-41d4-a716-446655440001');

        $currentUser = $this->createMock(Users::class);
        $currentUser->method('getId')->willReturn($sharedUuid);

        $toUser = $this->createMock(Users::class);
        $toUser->method('getId')->willReturn($sharedUuid);

        $message = $this->createMock(HelpMessages::class);
        $message->method('isRead')->willReturn(true);
        $message->method('getToUser')->willReturn($toUser);
        $message->expects($this->never())->method('markAsRead');

        $this->security->method('getUser')->willReturn($currentUser);
        $this->repository->method('getThread')->willReturn([]);

        $result = $this->useCase->handler($message);

        $this->assertTrue($result['success']);
    }

    public function testHandlerReturnsFalseOnException(): void
    {
        $this->security->method('getUser')
            ->willThrowException(new \RuntimeException('Error'));

        $message = $this->createMock(HelpMessages::class);
        $this->log->expects($this->once())->method('handler');

        $result = $this->useCase->handler($message);

        $this->assertFalse($result['success']);
    }

    public function testHandlerReturnsThread(): void
    {
        $currentUser = $this->createMock(Users::class);
        $currentUser->method('getId')->willReturn(Uuid::fromString('550e8400-e29b-41d4-a716-446655440001'));

        $toUser = $this->createMock(Users::class);
        $toUser->method('getId')->willReturn(Uuid::fromString('550e8400-e29b-41d4-a716-446655440002'));

        $message = $this->createMock(HelpMessages::class);
        $message->method('isRead')->willReturn(false);
        $message->method('getToUser')->willReturn($toUser);

        $threadRows = [
            ['id' => 'uuid-1', 'message' => 'Hola'],
            ['id' => 'uuid-2', 'message' => 'Respuesta'],
        ];

        $this->security->method('getUser')->willReturn($currentUser);
        $this->repository->method('getThread')->willReturn($threadRows);

        $result = $this->useCase->handler($message);

        $this->assertArrayHasKey('thread', $result);
        $this->assertCount(2, $result['thread']);
    }
}
