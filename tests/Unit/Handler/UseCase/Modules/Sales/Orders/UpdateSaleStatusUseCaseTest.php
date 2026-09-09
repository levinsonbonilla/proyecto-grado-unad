<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Sales\Orders;

use App\Entity\Configurations\Globals\Status;
use App\Entity\Products\Orders\Orders;
use App\Entity\Products\Orders\PaymentTransactions;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Handler\UseCase\Modules\Sales\Orders\UpdateSaleStatusUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Configurations\Globals\StatusRepository;
use App\Repository\Products\Orders\PaymentTransactionsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Uid\Uuid;

class UpdateSaleStatusUseCaseTest extends TestCase
{
    private StatusRepository&MockObject $statusRepository;
    private PaymentTransactionsRepository&MockObject $paymentTransactionsRepository;
    private CustomeEntityManagerInterface&MockObject $em;
    private MailerInterface&MockObject $mailer;
    private GetDomainDataInterface&MockObject $getDomainData;
    private LogInterface&MockObject $log;
    private Orders&MockObject $order;
    private UpdateSaleStatusUseCase $useCase;

    protected function setUp(): void
    {
        $this->statusRepository = $this->createMock(StatusRepository::class);
        $this->paymentTransactionsRepository = $this->createMock(PaymentTransactionsRepository::class);
        $this->em     = $this->createMock(CustomeEntityManagerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->getDomainData = $this->createMock(GetDomainDataInterface::class);
        $this->log    = $this->createMock(LogInterface::class);
        $this->order  = $this->createMock(Orders::class);

        $domain = $this->createMock(Domains::class);
        $domain->method('getDomain')->willReturn('localhost');
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $user = $this->createMock(Users::class);
        $user->method('getEmail')->willReturn('cliente@test.com');
        $this->order->method('getUser')->willReturn($user);
        $this->order->method('getId')->willReturn(Uuid::v4());

        $this->useCase = new UpdateSaleStatusUseCase(
            $this->statusRepository,
            $this->paymentTransactionsRepository,
            $this->em,
            $this->mailer,
            $this->getDomainData,
            $this->log,
        );
    }

    private function statusMock(string $name): Status&MockObject
    {
        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn($name);
        return $status;
    }

    public function testHandlerRejectsInvalidTransition(): void
    {
        $this->order->method('getStatus')->willReturn($this->statusMock('Entregado'));

        $result = $this->useCase->handler($this->order, 'Pendiente');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No se puede pasar', $result['message']);
    }

    public function testHandlerMovesFromPendienteToProcesando(): void
    {
        $this->order->method('getStatus')->willReturn($this->statusMock('Pendiente'));
        $newStatus = $this->statusMock('Procesando');
        $this->statusRepository->method('findOneBy')->willReturn($newStatus);

        $this->order->expects($this->once())->method('edit')->with($newStatus)->willReturnSelf();
        $this->em->expects($this->atLeastOnce())->method('add');

        $result = $this->useCase->handler($this->order, 'Procesando');

        $this->assertTrue($result['success']);
    }

    public function testHandlerSetsTrackingWhenMovingToEnviado(): void
    {
        $this->order->method('getStatus')->willReturn($this->statusMock('Procesando'));
        $newStatus = $this->statusMock('Enviado');
        $this->statusRepository->method('findOneBy')->willReturn($newStatus);

        $this->order->expects($this->once())->method('setTracking')->with('ABC123', 'Servientrega')->willReturnSelf();
        $this->order->method('edit')->willReturnSelf();

        $result = $this->useCase->handler($this->order, 'Enviado', 'ABC123', 'Servientrega');

        $this->assertTrue($result['success']);
    }

    public function testHandlerUpdatesLatestTransactionWhenMovingToProcesando(): void
    {
        $this->order->method('getStatus')->willReturn($this->statusMock('Pendiente'));
        $this->order->method('edit')->willReturnSelf();
        $newStatus = $this->statusMock('Procesando');
        $this->statusRepository->method('findOneBy')->willReturn($newStatus);

        $transaction = $this->createMock(PaymentTransactions::class);
        $transaction->expects($this->once())->method('edit')->with($newStatus)->willReturnSelf();
        $this->paymentTransactionsRepository->method('findLatestByOrder')->willReturn($transaction);

        $result = $this->useCase->handler($this->order, 'Procesando');

        $this->assertTrue($result['success']);
    }

    public function testHandlerAllowsApprovingRetraction(): void
    {
        $this->order->method('getStatus')->willReturn($this->statusMock('Retracto Solicitado'));
        $this->order->method('edit')->willReturnSelf();
        $newStatus = $this->statusMock('Retracto Aprobado');
        $this->statusRepository->method('findOneBy')->willReturn($newStatus);

        $result = $this->useCase->handler($this->order, 'Retracto Aprobado');

        $this->assertTrue($result['success']);
    }

    public function testHandlerReturnsFalseWhenNewStatusNotFound(): void
    {
        $this->order->method('getStatus')->willReturn($this->statusMock('Pendiente'));
        $this->statusRepository->method('findOneBy')->willReturn(null);
        $this->log->method('handler')->willReturn(null);

        $result = $this->useCase->handler($this->order, 'Procesando');

        $this->assertFalse($result['success']);
    }

    public function testHandlerCatchesExceptionAndReturnsError(): void
    {
        $this->order->method('getStatus')->willReturn($this->statusMock('Pendiente'));
        $this->statusRepository->method('findOneBy')->willThrowException(new \RuntimeException('DB error'));
        $this->log->method('handler')->willReturn(null);

        $result = $this->useCase->handler($this->order, 'Procesando');

        $this->assertFalse($result['success']);
    }
}
