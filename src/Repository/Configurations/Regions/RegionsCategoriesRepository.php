<?php

namespace App\Repository\Configurations\Regions;

use App\Entity\Configurations\Regions\RegionsCategories;
use App\Entity\Products\Categories\Categories;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class RegionsCategoriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly CacheInterface $cache)
    {
        parent::__construct($registry, RegionsCategories::class);
    }

    public function getSelectedIds(Categories $entity, bool $useCache = true): array
    {

        $fetch = function () use ($entity): array {
            $rows = $this->createQueryBuilder('cc')
                ->join('cc.region', 'c')
                ->select('c.id')
                ->where('IDENTITY(cc.categories) = :categoryId')
                ->andWhere('cc.active = :active')
                ->setParameter('categoryId', $entity->getId(), 'uuid')
                ->setParameter('active', true)
                ->getQuery()
                ->getArrayResult();

            return array_map(fn(array $row) => (string) $row['id'], $rows);
        };

        if (!$useCache) {
            return $fetch();
        }

        return $this->cache->get(
            "categories_region_ids_{$entity->getId()}",
            function (ItemInterface $item) use ($fetch) {
                $item->expiresAfter(86400);
                return $fetch();
            }
        );
    }

    public function invalidateSelectedIdsCache(Categories $entity): void
    {
        $this->cache->delete("categories_region_ids_{$entity->getId()}");
    }
}
