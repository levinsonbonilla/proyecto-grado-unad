<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Sales\Orders;

use App\Entity\Configurations\Globals\Status;
use App\Entity\Products\Orders\Orders;
use App\Entity\Products\Orders\PaymentTransactions;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Handler\UseCase\Modules\Sales\Orders\GetSaleDetailUseCase;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Orders\OrdersProductsRepository;
use App\Repository\Products\Orders\OrdersRepository;
use App\Repository\Products\Orders\PaymentTransactionsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

class GetSaleDetailUseCaseTest extends TestCase
{
    private OrdersRepository&MockObject $ordersRepository;
    private OrdersProductsRepository&MockObject $ordersProductsRepository;
    private PaymentTransactionsRepository&MockObject $paymentTransactionsRepository;
    private LogInterface&MockObject $log;
    private RequestStack&MockObject $requestStack;
    private Orders&MockObject $order;
    private GetSaleDetailUseCase $useCase;

    protected function setUp(): void
    {
        $this->ordersRepository = $this->createMock(OrdersRepository::class);
        $this->ordersProductsRepository = $this->createMock(OrdersProductsRepository::class);
        $this->paymentTransactionsRepository = $this->createMock(PaymentTransactionsRepository::class);
        $this->log   = $this->createMock(LogInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->order = $this->createMock(Orders::class);

        $this->useCase = new GetSaleDetailUseCase(
            $this->ordersRepository,
            $this->ordersProductsRepository,
            $this->paymentTransactionsRepository,
            $this->log,
            $this->requestStack,
        );
    }

    public function testHandlerReturnsEmptyArrayWhenOrderNotFound(): void
    {
        $this->ordersRepository->method('getAdminDetail')->willReturn(null);

        $result = $this->useCase->handler($this->order);

        $this->assertSame([], $result);
    }

    public function testHandlerReturnsOrderItemsAndTransaction(): void
    {
        $this->ordersRepository->method('getAdminDetail')->willReturn(['id' => 'order-1', 'statusName' => 'Pendiente']);
        $this->ordersProductsRepository->method('findByOrder')->willReturn([
            ['productName' => 'Producto A', 'quantity' => '2', 'unitPrice' => '1000', 'totalPrice' => '2000'],
        ]);

        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn('Pendiente');
        $paymentMethod = $this->createMock(PaymentMethods::class);
        $paymentMethod->method('getName')->willReturn('Tarjeta de crédito');

        $transaction = $this->createMock(PaymentTransactions::class);
        $transaction->method('getAmount')->willReturn('2000');
        $transaction->method('getStatus')->willReturn($status);
        $transaction->method('getPaymentMethod')->willReturn($paymentMethod);
        $transaction->method('getGatewayReference')->willReturn('pi_123');

        $this->paymentTransactionsRepository->method('findLatestByOrder')->willReturn($transaction);

        $result = $this->useCase->handler($this->order);

        $this->assertSame('order-1', $result['order']['id']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('pi_123', $result['transaction']['gatewayReference']);
        $this->assertSame('Tarjeta de crédito', $result['transaction']['paymentMethodName']);
    }

    public function testHandlerReturnsNullTransactionWhenNoneExists(): void
    {
        $this->ordersRepository->method('getAdminDetail')->willReturn(['id' => 'order-1']);
        $this->ordersProductsRepository->method('findByOrder')->willReturn([]);
        $this->paymentTransactionsRepository->method('findLatestByOrder')->willReturn(null);

        $result = $this->useCase->handler($this->order);

        $this->assertNull($result['transaction']);
    }

    public function testHandlerReturnsEmptyArrayOnException(): void
    {
        $this->ordersRepository->method('getAdminDetail')->willThrowException(new \RuntimeException('Error'));
        $this->log->method('handler')->willReturn(null);

        $result = $this->useCase->handler($this->order);

        $this->assertSame([], $result);
    }
}
