<?php

namespace App\Tests\Unit\Handler\UseCase\Store\Orders;

use App\Entity\Users\Users;
use App\Handler\UseCase\Store\Orders\GetUserOrdersUseCase;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Orders\OrdersRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class GetUserOrdersUseCaseTest extends TestCase
{
    private Security&MockObject $security;
    private OrdersRepository&MockObject $ordersRepository;
    private LogInterface&MockObject $log;
    private RequestStack&MockObject $requestStack;
    private GetUserOrdersUseCase $useCase;

    protected function setUp(): void
    {
        $this->security         = $this->createMock(Security::class);
        $this->ordersRepository = $this->createMock(OrdersRepository::class);
        $this->log              = $this->createMock(LogInterface::class);
        $this->requestStack     = $this->createMock(RequestStack::class);

        $this->useCase = new GetUserOrdersUseCase(
            $this->security,
            $this->ordersRepository,
            $this->log,
            $this->requestStack,
        );
    }

    public function testHandlerReturnsEmptyArrayWhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $result = $this->useCase->handler();
        $this->assertSame([], $result);
    }

    public function testHandlerReturnsOrdersForAuthenticatedUser(): void
    {
        $user = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($user);

        $fakeOrders = [
            ['id' => 'abc', 'totalAmount' => '5000', 'statusName' => 'Pendiente'],
        ];
        $this->ordersRepository->method('findByUser')->willReturn($fakeOrders);

        $result = $this->useCase->handler();
        $this->assertCount(1, $result);
        $this->assertSame('Pendiente', $result[0]['statusName']);
    }

    public function testHandlerReturnsEmptyArrayOnException(): void
    {
        $user = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($user);
        $this->ordersRepository->method('findByUser')->willThrowException(new \RuntimeException('DB error'));
        $this->log->expects($this->once())->method('handler');

        $result = $this->useCase->handler();
        $this->assertSame([], $result);
    }
}
