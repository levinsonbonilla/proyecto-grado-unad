<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Products\Manager;

use App\Handler\UseCase\Modules\Products\Manager\ListManagerUseCase;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\ProductsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ListManagerUseCaseTest extends TestCase
{
    private ProductsRepository&MockObject $productsRepository;
    private ListDataTableInterface&MockObject $listDataTable;
    private LogInterface&MockObject $log;
    private ListManagerUseCase $useCase;

    protected function setUp(): void
    {
        $this->productsRepository = $this->createMock(ProductsRepository::class);
        $this->listDataTable = $this->createMock(ListDataTableInterface::class);
        $this->log = $this->createMock(LogInterface::class);

        $this->useCase = new ListManagerUseCase(
            $this->productsRepository,
            $this->listDataTable,
            $this->log,
        );
    }

    public function testHandlerReturnsRequiredDataTableKeys(): void
    {

        $this->productsRepository->method('getProductsList')
            ->willReturnOnConsecutiveCalls(
                [['id' => 'uuid-1', 'name' => 'Producto', 'active' => true]],
                ['total' => '2']
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
            ['id' => 'uuid-1', 'name' => 'Producto A', 'active' => true],
            ['id' => 'uuid-2', 'name' => 'Producto B', 'active' => true],
        ];

        $this->productsRepository->method('getProductsList')
            ->willReturnOnConsecutiveCalls($rows, ['total' => '2']);
        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertSame($rows, $result['data']);
        $this->assertCount(2, $result['data']);
    }

    public function testHandlerReturnsTotalCount(): void
    {
        $this->productsRepository->method('getProductsList')
            ->willReturnOnConsecutiveCalls(
                [['id' => 'uuid-1', 'name' => 'Test']],
                ['total' => '7']
            );
        $this->listDataTable->method('getDraw')->willReturn('4');

        $result = $this->useCase->handler();

        $this->assertSame('7', $result['recordsTotal']);
        $this->assertSame('7', $result['recordsFiltered']);
        $this->assertSame('4', $result['draw']);
    }

    public function testHandlerReturnsEmptyDataOnException(): void
    {
        $this->productsRepository->method('getProductsList')
            ->willThrowException(new \RuntimeException('Error de repositorio'));
        $this->log->method('handler')->willReturn(null);
        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertArrayHasKey('draw', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertSame([], $result['data']);
    }
}
