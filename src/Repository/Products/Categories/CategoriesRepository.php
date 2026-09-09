<?php

namespace App\Repository\Products\Categories;

use App\Entity\Products\Categories\Categories;
use App\Entity\Tenants\Domains\Domains;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\ListDataTableInterface;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;

class CategoriesRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ListDataTableInterface $listDataTable,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct($registry, Categories::class);
    }

    public function getCategoriesList(bool $isCount = false): ?array
    {
        $this->listDataTable->setRealColumns([
            "categories.id",
            "categories.name",
            "categories.description",
            "categories.active",
        ]);
        $query = $this->createQueryBuilder('categories');

        if ($isCount) {
            $query->select("COUNT(categories.id) as total");
        } else {
            $query->select($this->listDataTable->getRealColumns());
        }

        $query
            ->join("categories.domain", "domain")
            ->andWhere('domain.id = :domain')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($this->getDomainData->getDomainCache()));

        $query = $this->listDataTable->searchByAllColumns(
            $query
        );

        $query = $this->listDataTable->preGetQuery($query, $isCount);

        if ($isCount) {
            return $query->getQuery()->getSingleResult();
        }

        return $query->getQuery()->getResult();
    }

    public function getPublicActiveList(Domains $domain): array
    {
        return $this->createQueryBuilder('c')
            ->select(['c.id', 'c.name', 'c.description', 'c.image'])
            ->join('c.domain', 'd')
            ->where('d.id = :domain')
            ->andWhere('c.active = :active')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function invalidatePublicCache(Domains $domain): void
    {
        $id = $domain->getId();
        $this->cache->delete("store_categories_{$id}");
        $this->cache->delete("store_home_{$id}");
    }
}
