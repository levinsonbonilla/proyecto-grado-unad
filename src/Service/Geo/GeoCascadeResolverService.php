<?php

namespace App\Service\Geo;

use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;

final readonly class GeoCascadeResolverService implements GeoCascadeResolverInterface
{
    public function __construct(
        private RegionsRepository $regionsRepository,
        private CitiesRepository $citiesRepository,
    ) {
    }

    public function regionsForCountries(array $countryIds, string $locale): array
    {
        $countryIds = array_values(array_filter($countryIds));
        if (empty($countryIds)) {
            return [];
        }

        $regions = $this->regionsRepository->findActiveByCountryIds($countryIds);

        return $this->sortByName(array_map(fn ($r) => [
            'id'   => (string) $r->getId(),
            'name' => $r->getName($locale),
        ], $regions), $locale);
    }

    public function citiesForRegions(array $regionIds, string $locale): array
    {
        $regionIds = array_values(array_filter($regionIds));
        if (empty($regionIds)) {
            return [];
        }

        $cities = $this->citiesRepository->findActiveByRegionIds($regionIds);

        return $this->sortByName(array_map(fn ($c) => [
            'id'   => (string) $c->getId(),
            'name' => $c->getName($locale),
        ], $cities), $locale);
    }

    private function sortByName(array $rows, string $locale): array
    {
        $collator = new \Collator($locale);
        usort($rows, fn (array $a, array $b) => $collator->compare($a['name'], $b['name']));

        return $rows;
    }
}
