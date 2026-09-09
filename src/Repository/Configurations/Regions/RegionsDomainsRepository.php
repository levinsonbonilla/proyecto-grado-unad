<?php

namespace App\Repository\Configurations\Regions;

use App\Entity\Configurations\Regions\RegionsDomains;
use App\Entity\Tenants\Domains\Domains;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class RegionsDomainsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly CacheInterface $cache)
    {
        parent::__construct($registry, RegionsDomains::class);
    }

    public function getSelectedIds(Domains $entity, bool $useCache = true): array
    {
        $fetch = function () use ($entity): array {
            return array_map(
                'strval',
                $this->createQueryBuilder('cc')
                    ->join('cc.region', 'c')
                    ->select('c.id')
                    ->where('cc.domain = :entity')
                    ->andWhere('cc.active = :active')
                    ->setParameter('entity', $entity)
                    ->setParameter('active', true)
                    ->getQuery()
                    ->getSingleColumnResult()
            );
        };

        if (!$useCache) {
            return $fetch();
        }

        return $this->cache->get(
            "domains_region_ids_{$entity->getId()}",
            function (ItemInterface $item) use ($fetch) {
                $item->expiresAfter(86400);
                return $fetch();
            }
        );
    }

    public function invalidateSelectedIdsCache(Domains $entity): void
    {
        $this->cache->delete("domains_region_ids_{$entity->getId()}");
    }
}
