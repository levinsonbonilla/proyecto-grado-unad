<?php

namespace App\Service\Geo;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Util\GeoNameNormalizerUtil;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GeoDuplicateMergerService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function merge(bool $dryRun = false): array
    {
        $report = ['exact' => [], 'regions' => [], 'renamed' => [], 'cities' => []];
        $connection = $this->em->getConnection();

        $repointer = new GeoReferenceRepointer($connection);

        $connection->beginTransaction();
        try {
            $report['exact'] = $dryRun ? ['regions' => 0, 'cities' => 0] : $repointer->mergeExactDuplicates();
            $this->em->clear();

            foreach ($this->em->getRepository(Countries::class)->findAll() as $country) {
                $regions = $this->em->getRepository(Regions::class)->findBy(['country' => $country], ['createdAt' => 'ASC']);
                foreach ($this->groupDuplicates($regions, fn (Regions $r) => $r->getRawName()) as $group) {
                    $keep = $this->pickRegion($group);
                    foreach ($group as $duplicate) {
                        if ($duplicate === $keep) {
                            continue;
                        }
                        $report['regions'][] = sprintf('%s (%s) -> %s (%s)', $duplicate->getName('es'), $duplicate->getIsoCode(), $keep->getName('es'), $keep->getIsoCode());
                        if (!$dryRun) {
                            $repointer->repoint('regions', $duplicate->getId()->toBinary(), $keep->getId()->toBinary());
                        }
                    }
                }
            }

            $this->em->clear();
            $report['renamed'] = $this->cleanAdministrativeSuffixes($dryRun);
            $this->em->clear();

            foreach ($this->em->getRepository(Regions::class)->findAll() as $region) {
                $cities = $this->em->getRepository(Cities::class)->findBy(['region' => $region], ['createdAt' => 'ASC']);
                foreach ($this->groupDuplicates($cities, fn (Cities $c) => $c->getRawNames()) as $group) {
                    $keep = $this->pickCity($group);
                    foreach ($group as $duplicate) {
                        if ($duplicate === $keep) {
                            continue;
                        }
                        $report['cities'][] = sprintf('%s [%s] (%s) -> %s', $duplicate->getName('es'), $region->getName('es'), $duplicate->getGeoNameId(), $keep->getGeoNameId());
                        if (!$dryRun) {
                            $repointer->repoint('cities', $duplicate->getId()->toBinary(), $keep->getId()->toBinary());
                        }
                    }
                }
            }

            $dryRun ? $connection->rollBack() : $connection->commit();
        } catch (\Throwable $th) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $th;
        }

        $this->em->clear();

        return $report;
    }

    private function groupDuplicates(array $entities, callable $rawNames): array
    {
        $groups = [];
        foreach ($entities as $entity) {
            $key = GeoNameNormalizerUtil::primaryKey($rawNames($entity));
            if ($key !== null) {
                $groups[$key][] = $entity;
            }
        }

        return array_values(array_filter($groups, fn (array $group) => count($group) > 1));
    }

    private function pickRegion(array $group): Regions
    {
        foreach ($group as $region) {
            if (!ctype_digit($region->getIsoCode())) {
                return $region;
            }
        }

        return $group[0];
    }

    private function cleanAdministrativeSuffixes(bool $dryRun): array
    {
        $renamed = [];
        foreach ($this->em->getRepository(Regions::class)->findAll() as $region) {
            $names = json_decode($region->getRawName(), true);
            if (!is_array($names)) {
                continue;
            }
            if (!isset($names['es']) || !is_string($names['es'])) {
                continue;
            }
            $clean = $names;
            $clean['es'] = GeoNameNormalizerUtil::stripAdministrativeSuffix($names['es']);
            if ($clean === $names) {
                continue;
            }
            $renamed[] = sprintf('%s -> %s', $region->getName('es'), $clean['es'] ?? reset($clean));
            if (!$dryRun) {
                $this->em->getConnection()->executeStatement(
                    'UPDATE regions SET name = ? WHERE id = ?',
                    [json_encode($clean, JSON_UNESCAPED_UNICODE), $region->getId()->toBinary()]
                );
            }
        }

        return $renamed;
    }

    private function pickCity(array $group): Cities
    {
        foreach ($group as $city) {
            if (ctype_digit($city->getGeoNameId())) {
                return $city;
            }
        }

        return $group[0];
    }
}
