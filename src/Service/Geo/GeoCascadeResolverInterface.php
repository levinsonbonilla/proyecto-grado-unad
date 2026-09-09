<?php

namespace App\Service\Geo;

interface GeoCascadeResolverInterface
{

    public function regionsForCountries(array $countryIds, string $locale): array;

    public function citiesForRegions(array $regionIds, string $locale): array;
}
