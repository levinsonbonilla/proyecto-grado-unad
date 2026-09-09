<?php

namespace App\Tests\Unit\Handler\Configuration;

use App\Entity\Tenants\Domains\Domains;
use App\Handler\Configuration\GetDomainData;
use App\Interface\Configuration\ActiveDashboardDomainResolverInterface;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\Repository\Tenants\TenantsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class GetDomainDataTest extends TestCase
{
    private DomainsRepository&MockObject $domainsRepository;
    private TenantsRepository&MockObject $tenantsRepository;
    private ActiveDashboardDomainResolverInterface&MockObject $activeDomainResolver;
    private Domains&MockObject $hostDomain;
    private Domains&MockObject $overrideDomain;

    private function buildGetDomainData(string $pathInfo): GetDomainData
    {
        $request = $this->createMock(Request::class);
        $request->method('getSchemeAndHttpHost')->willReturn('http://localhost:8060');
        $request->method('getPathInfo')->willReturn($pathInfo);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        return new GetDomainData(
            $requestStack,
            $this->domainsRepository,
            $this->tenantsRepository,
            $this->activeDomainResolver,
        );
    }

    protected function setUp(): void
    {
        $this->domainsRepository = $this->createMock(DomainsRepository::class);
        $this->tenantsRepository = $this->createMock(TenantsRepository::class);
        $this->activeDomainResolver = $this->createMock(ActiveDashboardDomainResolverInterface::class);

        $this->hostDomain = $this->createMock(Domains::class);
        $this->overrideDomain = $this->createMock(Domains::class);

        $this->domainsRepository->method('getDomain')->willReturn($this->hostDomain);
        $this->domainsRepository->method('getDomainCache')->willReturn($this->hostDomain);
    }

    public function testGetDomainOutsideDashboardIgnoresResolverAndUsesHost(): void
    {
        $this->activeDomainResolver->expects($this->never())->method('resolve');

        $getDomainData = $this->buildGetDomainData('/es/store/some-product');

        $this->assertSame($this->hostDomain, $getDomainData->getDomain());
    }

    public function testGetDomainCacheOutsideDashboardIgnoresResolverAndUsesHost(): void
    {
        $this->activeDomainResolver->expects($this->never())->method('resolve');

        $getDomainData = $this->buildGetDomainData('/es/store/some-product');

        $this->assertSame($this->hostDomain, $getDomainData->getDomainCache());
    }

    public function testGetDomainInsideDashboardUsesOverrideWhenResolverReturnsOne(): void
    {
        $this->activeDomainResolver->method('resolve')->willReturn($this->overrideDomain);

        $getDomainData = $this->buildGetDomainData('/es/dashboard/products/manager');

        $this->assertSame($this->overrideDomain, $getDomainData->getDomain());
    }

    public function testGetDomainCacheInsideDashboardUsesOverrideWhenResolverReturnsOne(): void
    {
        $this->activeDomainResolver->method('resolve')->willReturn($this->overrideDomain);

        $getDomainData = $this->buildGetDomainData('/es/dashboard/products/manager');

        $this->assertSame($this->overrideDomain, $getDomainData->getDomainCache());
    }

    public function testGetDomainInsideDashboardFallsBackToHostWhenResolverReturnsNull(): void
    {
        $this->activeDomainResolver->method('resolve')->willReturn(null);

        $getDomainData = $this->buildGetDomainData('/es/dashboard/products/manager');

        $this->assertSame($this->hostDomain, $getDomainData->getDomain());
    }
}
