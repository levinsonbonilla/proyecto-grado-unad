<?php

namespace App\Tests\Unit\Handler\UseCase\Messages;

use App\Entity\Users\Users;
use App\Handler\UseCase\Messages\UnreadCountUseCase;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\HelpMessagesRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class UnreadCountUseCaseTest extends TestCase
{
    private Security&MockObject $security;
    private HelpMessagesRepository&MockObject $repository;
    private LogInterface&MockObject $log;
    private UnreadCountUseCase $useCase;

    protected function setUp(): void
    {
        $this->security    = $this->createMock(Security::class);
        $this->repository  = $this->createMock(HelpMessagesRepository::class);
        $this->log         = $this->createMock(LogInterface::class);

        $this->useCase = new UnreadCountUseCase(
            $this->security,
            $this->repository,
            $this->log,
        );
    }

    public function testHandlerReturnsUnreadCount(): void
    {
        $user = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($user);
        $this->repository->method('countUnread')->with($user)->willReturn(5);

        $result = $this->useCase->handler();

        $this->assertSame(['count' => 5], $result);
    }

    public function testHandlerReturnsZeroWhenNoUnread(): void
    {
        $user = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($user);
        $this->repository->method('countUnread')->willReturn(0);

        $result = $this->useCase->handler();

        $this->assertSame(['count' => 0], $result);
    }

    public function testHandlerReturnsZeroOnException(): void
    {
        $this->security->method('getUser')
            ->willThrowException(new \RuntimeException('Error de seguridad'));

        $this->log->expects($this->once())->method('handler');

        $result = $this->useCase->handler();

        $this->assertSame(['count' => 0], $result);
    }
}
