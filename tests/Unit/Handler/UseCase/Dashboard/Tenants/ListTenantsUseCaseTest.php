<?php

namespace App\Tests\Unit\Handler\UseCase\Dashboard\Tenants;

use App\Exception\GenericException;
use App\Handler\UseCase\Dashboard\Tenants\ListTenantsUseCase;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\TenantsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class ListTenantsUseCaseTest extends TestCase
{
    private TenantsRepository&MockObject $tenantsRepository;
    private Security&MockObject $security;
    private ListDataTableInterface&MockObject $listDataTable;
    private LogInterface&MockObject $log;
    private ListTenantsUseCase $useCase;

    protected function setUp(): void
    {
        $this->tenantsRepository = $this->createMock(TenantsRepository::class);
        $this->security          = $this->createMock(Security::class);
        $this->listDataTable     = $this->createMock(ListDataTableInterface::class);
        $this->log               = $this->createMock(LogInterface::class);

        $this->useCase = new ListTenantsUseCase(
            $this->tenantsRepository,
            $this->security,
            $this->listDataTable,
            $this->log
        );
    }

    public function testHandlerReturnsRequiredDataTableKeys(): void
    {
        $this->tenantsRepository->method('getTenantList')
            ->willReturnOnConsecutiveCalls(
                [['id' => 'uuid-1', 'name' => 'Empresa 1', 'active' => true]],
                [['total' => '1']]
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
        $tenantRows = [
            ['id' => 'uuid-1', 'name' => 'Empresa Uno', 'active' => true],
            ['id' => 'uuid-2', 'name' => 'Empresa Dos', 'active' => true],
        ];

        $this->tenantsRepository->method('getTenantList')
            ->willReturnOnConsecutiveCalls(
                $tenantRows,
                [['total' => '2']]
            );

        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertSame($tenantRows, $result['data']);
        $this->assertCount(2, $result['data']);
    }

    public function testHandlerReturnsTotalCount(): void
    {

        $this->tenantsRepository->method('getTenantList')
            ->willReturnOnConsecutiveCalls(
                [['id' => 'uuid-1', 'name' => 'Empresa']],
                ['total' => '5']
            );

        $this->listDataTable->method('getDraw')->willReturn('2');

        $result = $this->useCase->handler();

        $this->assertSame('5', $result['recordsTotal']);
        $this->assertSame('5', $result['recordsFiltered']);
        $this->assertSame('2', $result['draw']);
    }

    public function testHandlerReturnsEmptyDataOnGenericException(): void
    {
        $this->tenantsRepository->method('getTenantList')
            ->willThrowException(new GenericException('Error de repositorio', 500));

        $this->log->method('handler')->willReturn(null);

        $result = $this->useCase->handler();

        $this->assertSame(1, $result['draw']);
        $this->assertSame(0, $result['recordsTotal']);
        $this->assertSame(0, $result['recordsFiltered']);
        $this->assertEmpty($result['data']);
    }

    public function testHandlerReturnsEmptyDataOnUnexpectedException(): void
    {
        $this->tenantsRepository->method('getTenantList')
            ->willThrowException(new \RuntimeException('Error inesperado'));

        $this->log->method('handler')->willReturn(null);

        $result = $this->useCase->handler();

        $this->assertSame(1, $result['draw']);
        $this->assertSame(0, $result['recordsTotal']);
        $this->assertEmpty($result['data']);
    }

    public function testHandlerCallsRepositoryWithIsCountTrue(): void
    {
        $this->tenantsRepository
            ->expects($this->exactly(2))
            ->method('getTenantList')
            ->withConsecutive([], [true]);

        $this->tenantsRepository->method('getTenantList')
            ->willReturnOnConsecutiveCalls(
                [],
                [['total' => '0']]
            );

        $this->listDataTable->method('getDraw')->willReturn('1');

        $this->useCase->handler();
    }
}
