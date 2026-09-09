<?php

namespace App\Service\Geo;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;

final class GeoLocation
{
    public function __construct(
        public readonly ?Countries $country,
        public readonly ?Regions $region,
        public readonly ?Cities $city,
    ) {}

    public function getCountryId(): ?string
    {
        return $this->country ? (string) $this->country->getId() : null;
    }

    public function getRegionId(): ?string
    {
        return $this->region ? (string) $this->region->getId() : null;
    }

    public function getCityId(): ?string
    {
        return $this->city ? (string) $this->city->getId() : null;
    }

    public function hasGeo(): bool
    {
        return $this->country !== null;
    }
}
