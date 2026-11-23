<?php

namespace App\Service\Geo;

use App\ArgumentHandler\CitiesArgument;
use App\ArgumentHandler\CountriesArgument;
use App\ArgumentHandler\RegionsArgument;
use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Util\GeoNameNormalizerUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Uid\Uuid;

final class GeoCatalogService implements GeoCatalogInterface
{
    private array $pendingRegions = [];
    private array $pendingCities = [];

    public function __construct(
        private readonly RegionsRepository $regionsRepository,
        private readonly CitiesRepository $citiesRepository,
        private readonly CustomeEntityManagerInterface $em,
        private readonly ?Connection $connection = null,
        private readonly ?CountriesRepository $countriesRepository = null,
    ) {
    }

    public function ensureCountry(string $isoCode, string $namesJson, string $description = 'País'): Countries
    {
        $existing = $this->countriesRepository?->findOneBy(['isoCode' => $isoCode]);
        if ($existing !== null) {
            return $existing;
        }

        if ($this->connection === null || $this->countriesRepository === null) {
            $country = (new Countries())->add(new CountriesArgument([
                'name'        => $namesJson,
                'description' => $description,
                'isoCode'     => $isoCode,
            ]));
            $this->em->add($country, true);
            return $country;
        }

        return $this->insertCountryOrFetch($isoCode, $namesJson, $description);
    }

    public function insertCountryOrFetch(string $isoCode, string $namesJson, string $description = 'País'): Countries
    {
        $this->insertIgnoringDuplicate('countries', [
            'name'        => $namesJson,
            'description' => $description,
            'iso_code'    => $isoCode,
        ]);

        return $this->countriesRepository->findOneBy(['isoCode' => $isoCode]);
    }

    public function ensureRegion(Countries $country, string $isoCode, string $namesJson, string $description = 'Región'): Regions
    {
        $existing = $this->findRegion($country, $isoCode, $namesJson);
        if ($existing !== null) {
            return $existing;
        }

        if ($this->connection === null) {
            $region = $this->findOrCreateRegion($country, $isoCode, $namesJson, $description);
            $this->em->flush();
            return $region;
        }

        return $this->insertRegionOrFetch($country, $isoCode, $namesJson, $description);
    }

    public function ensureCity(Regions $region, string $geoNameId, string $namesJson, string $description = 'Ciudad'): Cities
    {
        $existing = $this->findCity($region, $geoNameId, $namesJson);
        if ($existing !== null) {
            return $existing;
        }

        if ($this->connection === null) {
            $city = $this->findOrCreateCity($region, $geoNameId, $namesJson, $description);
            $this->em->flush();
            return $city;
        }

        return $this->insertCityOrFetch($region, $geoNameId, $namesJson, $description);
    }

    public function insertRegionOrFetch(Countries $country, string $isoCode, string $namesJson, string $description = 'Región'): Regions
    {
        $this->insertIgnoringDuplicate('regions', [
            'country_id'  => $country->getId()->toBinary(),
            'name'        => $namesJson,
            'description' => $description,
            'iso_code'    => $isoCode,
        ]);

        return $this->regionsRepository->findOneBy(['isoCode' => $isoCode, 'country' => $country]);
    }

    public function insertCityOrFetch(Regions $region, string $geoNameId, string $namesJson, string $description = 'Ciudad'): Cities
    {
        $this->insertIgnoringDuplicate('cities', [
            'region_id'   => $region->getId()->toBinary(),
            'names'       => $namesJson,
            'description' => $description,
            'name'        => $geoNameId,
        ]);

        return $this->citiesRepository->findOneBy(['name' => $geoNameId]);
    }

    private function insertIgnoringDuplicate(string $table, array $values): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        try {
            $this->connection->insert($table, $values + [
                'id'         => Uuid::v7()->toBinary(),
                'active'     => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], ['active' => Types::BOOLEAN]);
        } catch (UniqueConstraintViolationException) {
        }
    }

    public function findRegion(Countries $country, string $isoCode, string $namesJson): ?Regions
    {
        $byCode = $this->regionsRepository->findOneBy(['isoCode' => $isoCode, 'country' => $country]);
        if ($byCode !== null) {
            return $byCode;
        }

        $candidates = array_merge(
            $this->regionsRepository->findBy(['country' => $country]),
            $this->pendingRegions[$this->key($country)] ?? []
        );
        foreach ($candidates as $candidate) {
            if (GeoNameNormalizerUtil::sameName($namesJson, $candidate->getRawName())) {
                return $candidate;
            }
        }

        return null;
    }

    public function findOrCreateRegion(Countries $country, string $isoCode, string $namesJson, string $description = 'Región'): Regions
    {
        $existing = $this->findRegion($country, $isoCode, $namesJson);
        if ($existing !== null) {
            return $existing;
        }

        $region = (new Regions())->add(new RegionsArgument([
            'name'        => $namesJson,
            'description' => $description,
            'isoCode'     => $isoCode,
        ], $country));
        $this->em->add($region, false);
        $this->pendingRegions[$this->key($country)][] = $region;

        return $region;
    }

    public function findCity(Regions $region, string $geoNameId, string $namesJson): ?Cities
    {
        $byGeoNameId = $this->citiesRepository->findOneBy(['name' => $geoNameId]);
        if ($byGeoNameId !== null) {
            return $byGeoNameId;
        }

        $candidates = array_merge(
            $this->citiesRepository->findBy(['region' => $region]),
            $this->pendingCities[$this->key($region)] ?? []
        );
        foreach ($candidates as $candidate) {
            if (GeoNameNormalizerUtil::sameName($namesJson, $candidate->getRawNames())) {
                return $candidate;
            }
        }

        return null;
    }

    public function findOrCreateCity(Regions $region, string $geoNameId, string $namesJson, string $description = 'Ciudad'): Cities
    {
        $existing = $this->findCity($region, $geoNameId, $namesJson);
        if ($existing !== null) {
            return $existing;
        }

        $city = (new Cities())->add(new CitiesArgument([
            'name'        => $geoNameId,
            'description' => $description,
            'names'       => $namesJson,
        ], $region));
        $this->em->add($city, false);
        $this->pendingCities[$this->key($region)][] = $city;

        return $city;
    }

    private function key(object $entity): string
    {
        try {
            return (string) $entity->getId();
        } catch (\Error) {
            return 'object-' . spl_object_id($entity);
        }
    }
}
