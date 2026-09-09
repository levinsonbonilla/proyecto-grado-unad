<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Products\Colors;

use App\Handler\UseCase\Modules\Products\Colors\ListColorsUseCase;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Colors\ColorsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ListColorsUseCaseTest extends TestCase
{
    private ColorsRepository&MockObject $colorsRepository;
    private ListDataTableInterface&MockObject $listDataTable;
    private LogInterface&MockObject $log;
    private ListColorsUseCase $useCase;

    protected function setUp(): void
    {
        $this->colorsRepository = $this->createMock(ColorsRepository::class);
        $this->listDataTable = $this->createMock(ListDataTableInterface::class);
        $this->log = $this->createMock(LogInterface::class);

        $this->useCase = new ListColorsUseCase(
            $this->colorsRepository,
            $this->listDataTable,
            $this->log,
        );
    }

    public function testHandlerReturnsRequiredDataTableKeys(): void
    {

        $this->colorsRepository->method('getColorsList')
            ->willReturnOnConsecutiveCalls(
                [['id' => 'uuid-1', 'name' => 'Rojo', 'hexCode' => '#ff0000']],
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
            ['id' => 'uuid-1', 'name' => 'Rojo', 'hexCode' => '#ff0000'],
            ['id' => 'uuid-2', 'name' => 'Azul', 'hexCode' => '#0000ff'],
        ];

        $this->colorsRepository->method('getColorsList')
            ->willReturnOnConsecutiveCalls($rows, ['total' => '2']);
        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertSame($rows, $result['data']);
        $this->assertCount(2, $result['data']);
    }

    public function testHandlerReturnsTotalCount(): void
    {
        $this->colorsRepository->method('getColorsList')
            ->willReturnOnConsecutiveCalls(
                [['id' => 'uuid-1', 'name' => 'Rojo']],
                ['total' => '9']
            );
        $this->listDataTable->method('getDraw')->willReturn('2');

        $result = $this->useCase->handler();

        $this->assertSame('9', $result['recordsTotal']);
        $this->assertSame('9', $result['recordsFiltered']);
        $this->assertSame('2', $result['draw']);
    }

    public function testHandlerReturnsEmptyDataOnException(): void
    {
        $this->colorsRepository->method('getColorsList')
            ->willThrowException(new \RuntimeException('Error de repositorio'));
        $this->log->method('handler')->willReturn(null);
        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertArrayHasKey('draw', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertSame([], $result['data']);
    }
}
