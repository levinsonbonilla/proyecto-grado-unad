<?php

namespace App\Service\Geo;

use App\Entity\Configurations\Globals\Countries;

final readonly class GeoNamesImportResult
{
    public function __construct(
        public Countries $country,
        public bool $countryCreated,
        public int $regionsCreated,
        public int $regionsSkipped,
        public int $citiesCreated,
        public int $citiesSkipped,
    ) {
    }
}
