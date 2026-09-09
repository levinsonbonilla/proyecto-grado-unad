<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Products\Categories;

use App\Handler\UseCase\Modules\Products\Categories\ListCategoriesUseCase;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Categories\CategoriesRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ListCategoriesUseCaseTest extends TestCase
{
    private CategoriesRepository&MockObject $categoriesRepository;
    private ListDataTableInterface&MockObject $listDataTable;
    private LogInterface&MockObject $log;
    private ListCategoriesUseCase $useCase;

    protected function setUp(): void
    {
        $this->categoriesRepository = $this->createMock(CategoriesRepository::class);
        $this->listDataTable = $this->createMock(ListDataTableInterface::class);
        $this->log = $this->createMock(LogInterface::class);

        $this->useCase = new ListCategoriesUseCase(
            $this->categoriesRepository,
            $this->listDataTable,
            $this->log,
        );
    }

    public function testHandlerReturnsRequiredDataTableKeys(): void
    {

        $this->categoriesRepository->method('getCategoriesList')
            ->willReturnOnConsecutiveCalls(
                [['id' => 'uuid-1', 'name' => 'Electrónica']],
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
            ['id' => 'uuid-1', 'name' => 'Electrónica'],
            ['id' => 'uuid-2', 'name' => 'Hogar'],
        ];

        $this->categoriesRepository->method('getCategoriesList')
            ->willReturnOnConsecutiveCalls($rows, ['total' => '2']);
        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertSame($rows, $result['data']);
        $this->assertCount(2, $result['data']);
    }

    public function testHandlerReturnsTotalCount(): void
    {
        $this->categoriesRepository->method('getCategoriesList')
            ->willReturnOnConsecutiveCalls(
                [['id' => 'uuid-1', 'name' => 'Test']],
                ['total' => '4']
            );
        $this->listDataTable->method('getDraw')->willReturn('2');

        $result = $this->useCase->handler();

        $this->assertSame('4', $result['recordsTotal']);
        $this->assertSame('4', $result['recordsFiltered']);
        $this->assertSame('2', $result['draw']);
    }

    public function testHandlerReturnsEmptyDataOnException(): void
    {
        $this->categoriesRepository->method('getCategoriesList')
            ->willThrowException(new \RuntimeException('Error de repositorio'));
        $this->log->method('handler')->willReturn(null);
        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertArrayHasKey('draw', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertSame([], $result['data']);
    }
}
