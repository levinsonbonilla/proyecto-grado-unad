<?php

namespace App\Tests\Unit\Service\Geo;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Regions;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Service\Geo\GeoCascadeResolverService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class GeoCascadeResolverServiceTest extends TestCase
{
    private RegionsRepository&MockObject $regionsRepository;
    private CitiesRepository&MockObject $citiesRepository;
    private GeoCascadeResolverService $resolver;

    protected function setUp(): void
    {
        $this->regionsRepository = $this->createMock(RegionsRepository::class);
        $this->citiesRepository  = $this->createMock(CitiesRepository::class);
        $this->resolver = new GeoCascadeResolverService($this->regionsRepository, $this->citiesRepository);
    }

    public function testRegionsForCountriesReturnsEmptyArrayWithoutQueryingWhenNoIds(): void
    {
        $this->regionsRepository->expects($this->never())->method('findActiveByCountryIds');

        $result = $this->resolver->regionsForCountries([], 'es');

        $this->assertSame([], $result);
    }

    public function testRegionsForCountriesUsesRequestedLocale(): void
    {
        $id     = (string) Uuid::v4();
        $region = $this->createMock(Regions::class);
        $region->method('getId')->willReturn(Uuid::fromString($id));
        $region->method('getName')->with('es')->willReturn('Bogotá D.C.');

        $this->regionsRepository->method('findActiveByCountryIds')->willReturn([$region]);

        $result = $this->resolver->regionsForCountries(['country-id'], 'es');

        $this->assertSame([['id' => $id, 'name' => 'Bogotá D.C.']], $result);
    }

    public function testCitiesForRegionsFiltersOutEmptyIdsBeforeQuerying(): void
    {
        $id   = (string) Uuid::v4();
        $city = $this->createMock(Cities::class);
        $city->method('getId')->willReturn(Uuid::fromString($id));
        $city->method('getName')->with('en')->willReturn('Bogota');

        $this->citiesRepository->expects($this->once())
            ->method('findActiveByRegionIds')
            ->with(['region-id'])
            ->willReturn([$city]);

        $result = $this->resolver->citiesForRegions(['', 'region-id'], 'en');

        $this->assertSame([['id' => $id, 'name' => 'Bogota']], $result);
    }
}
