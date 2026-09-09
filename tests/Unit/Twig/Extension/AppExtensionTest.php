<?php

namespace App\Tests\Unit\Twig\Extension;

use App\Entity\Tenants\Domains\Domains;
use App\Interface\Configuration\ActiveDashboardDomainResolverInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Service\Currency\CurrentCurrencyResolverInterface;
use App\Interface\UseCase\Store\Products\GetPublicCategoriesInterface;
use App\Twig\Extension\AppExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AppExtensionTest extends TestCase
{
    private GetDomainDataInterface&MockObject $getDomainData;
    private RequestStack&MockObject $requestStack;
    private ActiveDashboardDomainResolverInterface&MockObject $activeDomainResolver;
    private GetPublicCategoriesInterface&MockObject $publicCategories;
    private CurrentCurrencyResolverInterface&MockObject $currencyResolver;
    private AppExtension $extension;

    protected function setUp(): void
    {
        $this->getDomainData = $this->createMock(GetDomainDataInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->activeDomainResolver = $this->createMock(ActiveDashboardDomainResolverInterface::class);
        $this->publicCategories = $this->createMock(GetPublicCategoriesInterface::class);
        $this->currencyResolver = $this->createMock(CurrentCurrencyResolverInterface::class);

        $this->extension = new AppExtension($this->getDomainData, $this->requestStack, $this->activeDomainResolver, $this->publicCategories, $this->currencyResolver);
    }

    public function testDashboardActiveDomainReturnsNullWhenNoCurrentRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $this->activeDomainResolver->expects($this->never())->method('resolve');

        $this->assertNull($this->extension->dashboardActiveDomain());
    }

    public function testDashboardActiveDomainDelegatesToResolver(): void
    {
        $request = new Request();
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        $domain = $this->createMock(Domains::class);
        $this->activeDomainResolver->method('resolve')->with($request)->willReturn($domain);

        $this->assertSame($domain, $this->extension->dashboardActiveDomain());
    }

    public function testDashboardSelectableDomainsDelegatesToResolver(): void
    {
        $domains = [$this->createMock(Domains::class), $this->createMock(Domains::class)];
        $this->activeDomainResolver->method('getSelectableDomains')->willReturn($domains);

        $this->assertSame($domains, $this->extension->dashboardSelectableDomains());
    }

    public function testStoreCurrentDomainDelegatesToGetDomainData(): void
    {
        $domain = $this->createMock(Domains::class);
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $this->assertSame($domain, $this->extension->storeCurrentDomain());
    }

    public function testStoreCurrentDomainReturnsNullOnFailure(): void
    {
        $this->getDomainData->method('getDomainCache')->willThrowException(new \RuntimeException('no domain resolved'));

        $this->assertNull($this->extension->storeCurrentDomain());
    }

    public function testStoreFooterCategoriesDelegatesToGetPublicCategories(): void
    {
        $categories = [['id' => 'uuid-1', 'name' => 'Zapatos']];
        $this->publicCategories->method('handler')->willReturn($categories);

        $this->assertSame($categories, $this->extension->storeFooterCategories());
    }
}
