<?php

namespace App\Tests\Unit\Service\Payments;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Repository\Configurations\Cities\CitiesPaymentMethodsRepository;
use App\Repository\Configurations\Countries\CountriesPaymentMethodsRepository;
use App\Repository\Configurations\Regions\RegionsPaymentMethodsRepository;
use App\Repository\Tenants\Domains\PaymentMethodsRepository;
use App\Service\Geo\GeoLocation;
use App\Service\Payments\PaymentMethodAvailabilityResolverService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PaymentMethodAvailabilityResolverServiceTest extends TestCase
{
    private PaymentMethodsRepository&MockObject $paymentMethodsRepository;
    private CountriesPaymentMethodsRepository&MockObject $countriesRepository;
    private RegionsPaymentMethodsRepository&MockObject $regionsRepository;
    private CitiesPaymentMethodsRepository&MockObject $citiesRepository;
    private PaymentMethodAvailabilityResolverService $resolver;

    protected function setUp(): void
    {
        $this->paymentMethodsRepository = $this->createMock(PaymentMethodsRepository::class);
        $this->countriesRepository      = $this->createMock(CountriesPaymentMethodsRepository::class);
        $this->regionsRepository        = $this->createMock(RegionsPaymentMethodsRepository::class);
        $this->citiesRepository         = $this->createMock(CitiesPaymentMethodsRepository::class);

        $this->resolver = new PaymentMethodAvailabilityResolverService(
            $this->paymentMethodsRepository,
            $this->countriesRepository,
            $this->regionsRepository,
            $this->citiesRepository,
        );
    }

    private function noGeo(): GeoLocation
    {
        return new GeoLocation(null, null, null);
    }

    public function testResolveKeepsMethodsWithoutGeoRestrictions(): void
    {
        $domain = $this->createMock(Domains::class);
        $pm     = $this->createMock(PaymentMethods::class);
        $this->paymentMethodsRepository->method('findBy')->willReturn([$pm]);

        $this->countriesRepository->method('getSelectedIds')->willReturn([]);
        $this->regionsRepository->method('getSelectedIds')->willReturn([]);
        $this->citiesRepository->method('getSelectedIds')->willReturn([]);

        $result = $this->resolver->resolve($domain, $this->noGeo());

        $this->assertSame([$pm], $result);
    }

    public function testResolveFiltersOutMethodRestrictedToOtherCountry(): void
    {
        $domain = $this->createMock(Domains::class);
        $pm     = $this->createMock(PaymentMethods::class);
        $this->paymentMethodsRepository->method('findBy')->willReturn([$pm]);

        $this->countriesRepository->method('getSelectedIds')->willReturn(['country-only-id']);
        $this->regionsRepository->method('getSelectedIds')->willReturn([]);
        $this->citiesRepository->method('getSelectedIds')->willReturn([]);

        $result = $this->resolver->resolve($domain, $this->noGeo());

        $this->assertSame([], $result, 'sin ubicación conocida, un método restringido por país no debe listarse');
    }

    public function testResolveKeepsMethodWhenLocationMatchesCountryRestriction(): void
    {
        $domain  = $this->createMock(Domains::class);
        $pm      = $this->createMock(PaymentMethods::class);
        $country = $this->createMock(\App\Entity\Configurations\Globals\Countries::class);
        $country->method('getId')->willReturn(\Symfony\Component\Uid\Uuid::fromString('11111111-1111-1111-1111-111111111111'));

        $this->paymentMethodsRepository->method('findBy')->willReturn([$pm]);
        $this->countriesRepository->method('getSelectedIds')->willReturn(['11111111-1111-1111-1111-111111111111']);
        $this->regionsRepository->method('getSelectedIds')->willReturn([]);
        $this->citiesRepository->method('getSelectedIds')->willReturn([]);

        $result = $this->resolver->resolve($domain, new GeoLocation($country, null, null));

        $this->assertSame([$pm], $result);
    }

    public function testIsAvailableChecksSinglePaymentMethod(): void
    {
        $pm = $this->createMock(PaymentMethods::class);
        $this->countriesRepository->method('getSelectedIds')->willReturn([]);
        $this->regionsRepository->method('getSelectedIds')->willReturn([]);
        $this->citiesRepository->method('getSelectedIds')->willReturn([]);

        $this->assertTrue($this->resolver->isAvailable($pm, $this->noGeo()));
    }
}
