<?php

namespace App\Service\Geo;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;

interface GeoCatalogInterface
{
    public function findRegion(Countries $country, string $isoCode, string $namesJson): ?Regions;

    public function findOrCreateRegion(Countries $country, string $isoCode, string $namesJson, string $description = 'Región'): Regions;

    public function ensureRegion(Countries $country, string $isoCode, string $namesJson, string $description = 'Región'): Regions;

    public function ensureCity(Regions $region, string $geoNameId, string $namesJson, string $description = 'Ciudad'): Cities;

    public function findCity(Regions $region, string $geoNameId, string $namesJson): ?Cities;

    public function findOrCreateCity(Regions $region, string $geoNameId, string $namesJson, string $description = 'Ciudad'): Cities;
}
