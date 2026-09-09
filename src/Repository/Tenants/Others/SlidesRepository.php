<?php

namespace App\Repository\Tenants\Others;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Others\Slides;
use App\Interface\Configuration\ListDataTableInterface;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use GeoIp2\Model\Domain;
use Symfony\Contracts\Cache\CacheInterface;

class SlidesRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ListDataTableInterface $listDataTable,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct($registry, Slides::class);
    }

    public function getList(Domains $domain, bool $isCount = false): ?array
    {
        $this->listDataTable->setRealColumns([
            "slice.active",
            "slice.name",
            "slice.position",
            "slice.description",
            "slice.id",
        ]);
        $query = $this->createQueryBuilder('slice');

        if ($isCount) {
            $query->select("COUNT(slice.id) as total");
        } else {
            $query->select($this->listDataTable->getRealColumns());
        }

        $query
            ->andWhere("slice.domain = :domainId")
            ->setParameter("domainId", UUIDUtil::convertIdToSearch($domain));

        return $this->listDataTable->complementQueryResult($query, $isCount);
    }

    public function getPublicActiveList(Domains $domain): array
    {
        return $this->createQueryBuilder('s')
            ->select(['s.id', 's.name', 's.description', 's.image', 's.type', 's.position'])
            ->join('s.domain', 'd')
            ->where('d.id = :domain')
            ->andWhere('s.active = :active')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->setParameter('active', true)
            ->orderBy('s.position', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function invalidatePublicCache(Domains $domain): void
    {
        $id = $domain->getId();
        $this->cache->delete("store_slides_{$id}");
        $this->cache->delete("store_home_{$id}");
    }
}
