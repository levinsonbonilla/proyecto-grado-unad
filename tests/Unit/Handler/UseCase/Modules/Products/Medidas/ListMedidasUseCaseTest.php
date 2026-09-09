<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Products\Medidas;

use App\Handler\UseCase\Modules\Products\Medidas\ListMedidasUseCase;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Medidas\MedidasRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ListMedidasUseCaseTest extends TestCase
{
    private MedidasRepository&MockObject $medidasRepository;
    private ListDataTableInterface&MockObject $listDataTable;
    private LogInterface&MockObject $log;
    private ListMedidasUseCase $useCase;

    protected function setUp(): void
    {
        $this->medidasRepository = $this->createMock(MedidasRepository::class);
        $this->listDataTable = $this->createMock(ListDataTableInterface::class);
        $this->log = $this->createMock(LogInterface::class);

        $this->useCase = new ListMedidasUseCase(
            $this->medidasRepository,
            $this->listDataTable,
            $this->log,
        );
    }

    public function testHandlerReturnsRequiredDataTableKeys(): void
    {

        $this->medidasRepository->method('getMedidasList')
            ->willReturnOnConsecutiveCalls(
                [['id' => 'uuid-1', 'name' => 'M']],
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
            ['id' => 'uuid-1', 'name' => 'M'],
            ['id' => 'uuid-2', 'name' => 'L'],
        ];

        $this->medidasRepository->method('getMedidasList')
            ->willReturnOnConsecutiveCalls($rows, ['total' => '2']);
        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertSame($rows, $result['data']);
        $this->assertCount(2, $result['data']);
    }

    public function testHandlerReturnsTotalCount(): void
    {
        $this->medidasRepository->method('getMedidasList')
            ->willReturnOnConsecutiveCalls(
                [['id' => 'uuid-1', 'name' => 'M']],
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
        $this->medidasRepository->method('getMedidasList')
            ->willThrowException(new \RuntimeException('Error de repositorio'));
        $this->log->method('handler')->willReturn(null);
        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertArrayHasKey('draw', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertSame([], $result['data']);
    }
}
