<?php

namespace App\Tests\Unit\Util;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Service\Geo\GeoLocation;
use App\Util\GeoMatchUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class GeoMatchUtilTest extends TestCase
{
    private function makeGeo(?string $countryId, ?string $regionId = null, ?string $cityId = null): GeoLocation
    {
        $country = null;
        if ($countryId !== null) {
            $country = $this->createMock(Countries::class);
            $country->method('getId')->willReturn(Uuid::fromString($countryId));
        }
        $region = null;
        if ($regionId !== null) {
            $region = $this->createMock(Regions::class);
            $region->method('getId')->willReturn(Uuid::fromString($regionId));
        }
        $city = null;
        if ($cityId !== null) {
            $city = $this->createMock(Cities::class);
            $city->method('getId')->willReturn(Uuid::fromString($cityId));
        }

        return new GeoLocation($country, $region, $city);
    }

    public function testNoRestrictionsAtAnyLevelAlwaysMatches(): void
    {
        $this->assertTrue(GeoMatchUtil::matches([], [], [], new GeoLocation(null, null, null)));
        $this->assertTrue(GeoMatchUtil::matches([], [], [], $this->makeGeo(Uuid::v4()->toRfc4122())));
    }

    public function testCountryRestrictionRequiresMatchingCountry(): void
    {
        $countryId = Uuid::v4()->toRfc4122();
        $otherId   = Uuid::v4()->toRfc4122();

        $this->assertTrue(GeoMatchUtil::matches([$countryId], [], [], $this->makeGeo($countryId)));
        $this->assertFalse(GeoMatchUtil::matches([$countryId], [], [], $this->makeGeo($otherId)));
        $this->assertFalse(GeoMatchUtil::matches([$countryId], [], [], new GeoLocation(null, null, null)));
    }

    public function testAllThreeLevelsMustMatchWhenAllRestricted(): void
    {
        $countryId = Uuid::v4()->toRfc4122();
        $regionId  = Uuid::v4()->toRfc4122();
        $cityId    = Uuid::v4()->toRfc4122();
        $otherCity = Uuid::v4()->toRfc4122();

        $this->assertTrue(GeoMatchUtil::matches(
            [$countryId], [$regionId], [$cityId],
            $this->makeGeo($countryId, $regionId, $cityId)
        ));

        $this->assertFalse(GeoMatchUtil::matches(
            [$countryId], [$regionId], [$cityId],
            $this->makeGeo($countryId, $regionId, $otherCity)
        ));
    }

    public function testRestrictionOnlyAtCityLevelIgnoresCountryAndRegion(): void
    {
        $cityId = Uuid::v4()->toRfc4122();

        $this->assertTrue(GeoMatchUtil::matches(
            [], [], [$cityId],
            $this->makeGeo(Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122(), $cityId)
        ));
    }
}
