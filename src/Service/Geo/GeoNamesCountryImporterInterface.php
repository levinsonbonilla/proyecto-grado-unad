<?php

namespace App\Service\Geo;

interface GeoNamesCountryImporterInterface
{

    public function importCountry(string $isoCode, int $minPopulation = 5000): GeoNamesImportResult;
}
