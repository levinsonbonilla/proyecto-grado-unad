<?php

namespace App\Tests\Unit\Handler\UseCase\Store\Checkout;

use App\Entity\Configurations\Globals\Status;
use App\Entity\Products\Orders\Orders;
use App\Entity\Products\Orders\PaymentTransactions;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Handler\UseCase\Store\Checkout\HandlePaymentWebhookUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Configurations\Globals\StatusRepository;
use App\Repository\Products\Orders\OrdersProductsRepository;
use App\Repository\Products\Orders\PaymentTransactionsRepository;
use App\Service\Currency\CurrencyFormat;
use App\Interface\Service\Currency\CurrentCurrencyResolverInterface;
use App\Service\Products\VariantLabelFormatterInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Uid\Uuid;

class HandlePaymentWebhookUseCaseTest extends TestCase
{
    private const SECRET = 'test-secret';

    private PaymentTransactionsRepository&MockObject $paymentTransactionsRepository;
    private OrdersProductsRepository&MockObject $ordersProductsRepository;
    private StatusRepository&MockObject $statusRepository;
    private CustomeEntityManagerInterface&MockObject $em;
    private MailerInterface&MockObject $mailer;
    private GetDomainDataInterface&MockObject $getDomainData;
    private LogInterface&MockObject $log;
    private CurrentCurrencyResolverInterface&MockObject $currencyResolver;
    private VariantLabelFormatterInterface&MockObject $variantLabelFormatter;
    private HandlePaymentWebhookUseCase $useCase;

    protected function setUp(): void
    {
        $this->paymentTransactionsRepository = $this->createMock(PaymentTransactionsRepository::class);
        $this->ordersProductsRepository = $this->createMock(OrdersProductsRepository::class);
        $this->statusRepository = $this->createMock(StatusRepository::class);
        $this->em     = $this->createMock(CustomeEntityManagerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->getDomainData = $this->createMock(GetDomainDataInterface::class);
        $this->log    = $this->createMock(LogInterface::class);
        $this->currencyResolver = $this->createMock(CurrentCurrencyResolverInterface::class);
        $this->currencyResolver->method('resolve')->willReturn(CurrencyFormat::legacyDefault());
        $this->variantLabelFormatter = $this->createMock(VariantLabelFormatterInterface::class);
        $this->variantLabelFormatter->method('format')->willReturn('');

        $this->useCase = new HandlePaymentWebhookUseCase(
            $this->paymentTransactionsRepository,
            $this->ordersProductsRepository,
            $this->statusRepository,
            $this->em,
            $this->mailer,
            $this->getDomainData,
            $this->log,
            self::SECRET,
            $this->currencyResolver,
            $this->variantLabelFormatter,
        );
    }

    private function sign(string $body): string
    {
        return hash_hmac('sha256', $body, self::SECRET);
    }

    public function testHandlerRejectsInvalidSignature(): void
    {
        $body = json_encode(['intentId' => 'pi_1', 'status' => 'succeeded']);
        $this->log->method('handler')->willReturn(null);

        $result = $this->useCase->handler($body, 'firma-incorrecta');

        $this->assertFalse($result['success']);
    }

    public function testHandlerIgnoresUnknownGatewayReference(): void
    {
        $body = json_encode(['intentId' => 'pi_desconocido', 'status' => 'succeeded']);
        $this->paymentTransactionsRepository->method('findByGatewayReference')->willReturn(null);

        $result = $this->useCase->handler($body, $this->sign($body));

        $this->assertTrue($result['success']);
    }

    public function testHandlerIgnoresAlreadyTerminalTransaction(): void
    {
        $body = json_encode(['intentId' => 'pi_1', 'status' => 'succeeded']);

        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn('Procesando');

        $transaction = $this->createMock(PaymentTransactions::class);
        $transaction->method('getStatus')->willReturn($status);
        $transaction->expects($this->never())->method('edit');

        $this->paymentTransactionsRepository->method('findByGatewayReference')->willReturn($transaction);

        $result = $this->useCase->handler($body, $this->sign($body));

        $this->assertTrue($result['success']);
    }

    public function testHandlerMarksProcesandoOnSucceededAndSendsEmail(): void
    {
        $body = json_encode(['intentId' => 'pi_1', 'status' => 'succeeded']);

        $currentStatus = $this->createMock(Status::class);
        $currentStatus->method('getName')->willReturn('Pendiente');

        $newStatus = $this->createMock(Status::class);
        $newStatus->method('getName')->willReturn('Procesando');
        $this->statusRepository->method('findOneBy')
            ->with(['name' => 'Procesando', 'active' => true])
            ->willReturn($newStatus);

        $order = $this->createMock(Orders::class);
        $order->method('getId')->willReturn(Uuid::v4());
        $order->method('getTotalAmount')->willReturn('5000');
        $order->expects($this->once())->method('edit')->with($newStatus)->willReturnSelf();

        $user = $this->createMock(Users::class);
        $user->method('getEmail')->willReturn('cliente@test.com');
        $order->method('getUser')->willReturn($user);

        $transaction = $this->createMock(PaymentTransactions::class);
        $transaction->method('getStatus')->willReturn($currentStatus);
        $transaction->method('getGatewayReference')->willReturn('pi_1');
        $transaction->method('getOrders')->willReturn($order);
        $transaction->expects($this->once())->method('edit')->with($newStatus)->willReturnSelf();

        $this->paymentTransactionsRepository->method('findByGatewayReference')->willReturn($transaction);
        $this->ordersProductsRepository->method('findByOrder')->willReturn([]);

        $domain = $this->createMock(Domains::class);
        $domain->method('getDomain')->willReturn('localhost');
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $this->mailer->expects($this->once())->method('send');

        $result = $this->useCase->handler($body, $this->sign($body));

        $this->assertTrue($result['success']);
    }

    public function testHandlerMarksCanceladoOnFailedWithoutSendingEmail(): void
    {
        $body = json_encode(['intentId' => 'pi_1', 'status' => 'failed']);

        $currentStatus = $this->createMock(Status::class);
        $currentStatus->method('getName')->willReturn('Pendiente');

        $newStatus = $this->createMock(Status::class);
        $newStatus->method('getName')->willReturn('Cancelado');
        $this->statusRepository->method('findOneBy')->willReturn($newStatus);

        $order = $this->createMock(Orders::class);
        $order->expects($this->once())->method('edit')->willReturnSelf();

        $transaction = $this->createMock(PaymentTransactions::class);
        $transaction->method('getStatus')->willReturn($currentStatus);
        $transaction->method('getOrders')->willReturn($order);
        $transaction->expects($this->once())->method('edit')->willReturnSelf();

        $this->paymentTransactionsRepository->method('findByGatewayReference')->willReturn($transaction);

        $this->mailer->expects($this->never())->method('send');

        $result = $this->useCase->handler($body, $this->sign($body));

        $this->assertTrue($result['success']);
    }
}
