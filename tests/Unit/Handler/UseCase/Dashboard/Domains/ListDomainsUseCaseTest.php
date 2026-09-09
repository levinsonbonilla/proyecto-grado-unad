<?php

namespace App\Tests\Unit\Handler\UseCase\Dashboard\Domains;

use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Handler\UseCase\Dashboard\Domains\ListDomainsUseCase;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Domains\DomainsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class ListDomainsUseCaseTest extends TestCase
{
    private ListDataTableInterface&MockObject $listDataTable;
    private LogInterface&MockObject $log;
    private DomainsRepository&MockObject $domainsRepository;
    private GetDomainDataInterface&MockObject $getDomainData;
    private Security&MockObject $security;
    private ListDomainsUseCase $useCase;

    protected function setUp(): void
    {
        $this->listDataTable = $this->createMock(ListDataTableInterface::class);
        $this->listDataTable->method('getDraw')->willReturn('1');
        $this->log = $this->createMock(LogInterface::class);
        $this->domainsRepository = $this->createMock(DomainsRepository::class);
        $this->getDomainData = $this->createMock(GetDomainDataInterface::class);
        $this->security = $this->createMock(Security::class);

        $this->useCase = new ListDomainsUseCase(
            $this->listDataTable,
            $this->log,
            $this->domainsRepository,
            $this->getDomainData,
            $this->security,
        );
    }

    public function testSuperAdminSeesAllTenantsDomains(): void
    {
        $superAdmin = $this->createMock(Users::class);
        $superAdmin->method('getRoles')->willReturn(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER']);
        $this->security->method('getUser')->willReturn($superAdmin);

        $this->getDomainData->expects($this->never())->method('getTenantCache');

        $this->domainsRepository->expects($this->exactly(2))
            ->method('getDomainsList')
            ->with(null)
            ->willReturnOnConsecutiveCalls([['id' => 1]], [['total' => '3']]);

        $result = $this->useCase->handler();

        $this->assertSame([['id' => 1]], $result['data']);
    }

    public function testRegularAdminSeesOnlyOwnTenantDomains(): void
    {
        $admin = $this->createMock(Users::class);
        $admin->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);
        $this->security->method('getUser')->willReturn($admin);

        $tenant = $this->createMock(Tenants::class);
        $this->getDomainData->expects($this->once())->method('getTenantCache')->willReturn($tenant);

        $this->domainsRepository->expects($this->exactly(2))
            ->method('getDomainsList')
            ->with($tenant)
            ->willReturnOnConsecutiveCalls([['id' => 1]], [['total' => '2']]);

        $result = $this->useCase->handler();

        $this->assertSame([['id' => 1]], $result['data']);
    }

    public function testHandlerReturnsEmptyDataOnException(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->getDomainData->method('getTenantCache')->willThrowException(new \RuntimeException('Error'));
        $this->log->expects($this->once())->method('handler');

        $result = $this->useCase->handler();

        $this->assertSame([], $result['data']);
        $this->assertSame(0, $result['recordsTotal']);
    }
}
