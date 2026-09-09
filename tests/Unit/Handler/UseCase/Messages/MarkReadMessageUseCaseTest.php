<?php

namespace App\Tests\Unit\Handler\UseCase\Messages;

use App\Entity\Users\HelpMessages;
use App\Handler\UseCase\Messages\MarkReadMessageUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MarkReadMessageUseCaseTest extends TestCase
{
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private LogInterface&MockObject $log;
    private HelpMessages&MockObject $message;
    private MarkReadMessageUseCase $useCase;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(CustomeEntityManagerInterface::class);
        $this->log           = $this->createMock(LogInterface::class);
        $this->message       = $this->createMock(HelpMessages::class);

        $this->useCase = new MarkReadMessageUseCase(
            $this->entityManager,
            $this->log,
        );
    }

    public function testHandlerMarksUnreadMessageAsRead(): void
    {
        $this->message->method('isRead')->willReturn(false);
        $this->message->expects($this->once())->method('markAsRead');
        $this->message->expects($this->never())->method('markAsUnread');
        $this->entityManager->expects($this->once())->method('add');

        $result = $this->useCase->handler($this->message);

        $this->assertTrue($result['success']);
    }

    public function testHandlerMarksReadMessageAsUnread(): void
    {
        $this->message->method('isRead')->willReturn(true);
        $this->message->expects($this->once())->method('markAsUnread');
        $this->message->expects($this->never())->method('markAsRead');
        $this->entityManager->expects($this->once())->method('add');

        $result = $this->useCase->handler($this->message);

        $this->assertTrue($result['success']);
    }

    public function testHandlerReturnsFalseOnException(): void
    {
        $this->message->method('isRead')->willReturn(false);
        $this->message->method('markAsRead')
            ->willThrowException(new \RuntimeException('Error'));

        $this->log->expects($this->once())->method('handler');

        $result = $this->useCase->handler($this->message);

        $this->assertFalse($result['success']);
    }

    public function testHandlerReturnsIsReadFlag(): void
    {
        $this->message->method('isRead')->willReturnOnConsecutiveCalls(true, true);

        $result = $this->useCase->handler($this->message);

        $this->assertArrayHasKey('isRead', $result);
    }
}
