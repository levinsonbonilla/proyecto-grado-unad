<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Products\PaymentMethods;

use App\Handler\UseCase\Modules\Products\PaymentMethods\ListPaymentMethodsUseCase;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Domains\PaymentMethodsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ListPaymentMethodsUseCaseTest extends TestCase
{
    private ListDataTableInterface&MockObject $listDataTable;
    private LogInterface&MockObject $log;
    private PaymentMethodsRepository&MockObject $paymentMethodsRepository;
    private GetDomainDataInterface&MockObject $getDomainData;
    private ListPaymentMethodsUseCase $useCase;

    protected function setUp(): void
    {
        $this->listDataTable           = $this->createMock(ListDataTableInterface::class);
        $this->log                     = $this->createMock(LogInterface::class);
        $this->paymentMethodsRepository = $this->createMock(PaymentMethodsRepository::class);
        $this->getDomainData           = $this->createMock(GetDomainDataInterface::class);

        $domain = $this->createMock(\App\Entity\Tenants\Domains\Domains::class);
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $this->useCase = new ListPaymentMethodsUseCase(
            $this->listDataTable,
            $this->log,
            $this->paymentMethodsRepository,
            $this->getDomainData,
        );
    }

    public function testHandlerReturnsRequiredDataTableKeys(): void
    {
        $this->paymentMethodsRepository->method('getList')
            ->willReturnOnConsecutiveCalls(
                [['total' => '1']],
                [['id' => 'uuid-1', 'name' => 'Efectivo', 'active' => true]]
            );

        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertArrayHasKey('draw', $result);
        $this->assertArrayHasKey('recordsTotal', $result);
        $this->assertArrayHasKey('recordsFiltered', $result);
        $this->assertArrayHasKey('data', $result);
    }

    public function testHandlerReturnsDataFromRepository(): void
    {
        $rows = [
            ['id' => 'uuid-1', 'name' => 'Efectivo', 'active' => true],
            ['id' => 'uuid-2', 'name' => 'Tarjeta', 'active' => true],
        ];

        $this->paymentMethodsRepository->method('getList')
            ->willReturnOnConsecutiveCalls(
                [['total' => '2']],
                $rows
            );

        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertSame($rows, $result['data']);
        $this->assertCount(2, $result['data']);
    }

    public function testHandlerReturnsTotalCount(): void
    {
        $this->paymentMethodsRepository->method('getList')
            ->willReturnOnConsecutiveCalls(
                ['total' => '3'],
                [['id' => 'uuid-1', 'name' => 'Test']]
            );

        $this->listDataTable->method('getDraw')->willReturn('2');

        $result = $this->useCase->handler();

        $this->assertSame('3', $result['recordsTotal']);
        $this->assertSame('3', $result['recordsFiltered']);
        $this->assertSame('2', $result['draw']);
    }

    public function testHandlerReturnsEmptyDataOnException(): void
    {
        $this->paymentMethodsRepository->method('getList')
            ->willThrowException(new \RuntimeException('Error de repositorio'));

        $this->log->method('handler')->willReturn(null);
        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertArrayHasKey('draw', $result);
        $this->assertArrayHasKey('data', $result);
    }
}
