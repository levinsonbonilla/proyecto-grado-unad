<?php

namespace App\Tests\Unit\Handler\UseCase\Store\Orders;

use App\Entity\Configurations\Globals\Status;
use App\Entity\Products\Orders\Orders;
use App\Entity\Users\Users;
use App\Exception\GenericException;
use App\Handler\UseCase\Store\Orders\CancelOrderUseCase;
use App\Interface\UseCase\Security\LogInterface;
use App\Service\Orders\OrderCancellationInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class CancelOrderUseCaseTest extends TestCase
{
    private Security&MockObject $security;
    private OrderCancellationInterface&MockObject $orderCancellation;
    private LogInterface&MockObject $log;
    private CancelOrderUseCase $useCase;

    protected function setUp(): void
    {
        $this->security          = $this->createMock(Security::class);
        $this->orderCancellation = $this->createMock(OrderCancellationInterface::class);
        $this->log               = $this->createMock(LogInterface::class);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $key) => $key);

        $this->useCase = new CancelOrderUseCase(
            $this->security,
            $this->orderCancellation,
            $this->log,
            $translator,
        );
    }

    private function makeUser(): Users&MockObject
    {
        $user = $this->createMock(Users::class);
        $user->method('getId')->willReturn(Uuid::v4());
        return $user;
    }

    private function makeOrder(Users $user, string $statusName): Orders&MockObject
    {
        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn($statusName);
        $order = $this->createMock(Orders::class);
        $order->method('getUser')->willReturn($user);
        $order->method('getStatus')->willReturn($status);
        return $order;
    }

    public function testHandlerCancelsPendingOrderThroughTheCancellationService(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user, 'Pendiente');
        $this->security->method('getUser')->willReturn($user);

        $this->orderCancellation->expects($this->once())
            ->method('cancel')
            ->with($order, ['Pendiente', 'Procesando'])
            ->willReturn(true);

        $result = $this->useCase->handler($order);
        $this->assertTrue($result['success']);
    }

    public function testHandlerCancelsProcessingOrder(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user, 'Procesando');
        $this->security->method('getUser')->willReturn($user);
        $this->orderCancellation->method('cancel')->willReturn(true);

        $result = $this->useCase->handler($order);
        $this->assertTrue($result['success']);
    }

    public function testHandlerRefusesToCancelDeliveredOrderWithoutCallingTheService(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user, 'Entregado');
        $this->security->method('getUser')->willReturn($user);
        $this->orderCancellation->expects($this->never())->method('cancel');

        $result = $this->useCase->handler($order);
        $this->assertFalse($result['success']);
        $this->assertSame('order_cancel_not_allowed', $result['message']);
    }

    public function testHandlerReportsFailureWhenAnotherRequestAlreadyCancelledTheOrder(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user, 'Pendiente');
        $this->security->method('getUser')->willReturn($user);
        $this->orderCancellation->method('cancel')->willReturn(false);

        $result = $this->useCase->handler($order);
        $this->assertFalse($result['success']);
        $this->assertSame('order_cancel_not_allowed', $result['message']);
    }

    public function testHandlerRefusesWhenUserDoesNotOwnOrder(): void
    {
        $owner     = $this->makeUser();
        $requester = $this->makeUser();
        $order     = $this->makeOrder($owner, 'Pendiente');
        $this->security->method('getUser')->willReturn($requester);
        $this->orderCancellation->expects($this->never())->method('cancel');

        $result = $this->useCase->handler($order);
        $this->assertFalse($result['success']);
    }

    public function testHandlerReturnsFalseWhenCancellationServiceFails(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user, 'Pendiente');
        $this->security->method('getUser')->willReturn($user);
        $this->orderCancellation->method('cancel')->willThrowException(new GenericException('Estado de cancelación no disponible.', 500));

        $result = $this->useCase->handler($order);
        $this->assertFalse($result['success']);
        $this->assertSame('order_cancel_error', $result['message']);
    }
}
