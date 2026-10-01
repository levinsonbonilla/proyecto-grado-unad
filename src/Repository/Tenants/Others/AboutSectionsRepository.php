<?php

namespace App\Repository\Tenants\Others;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Others\AboutSections;
use App\Interface\Configuration\ListDataTableInterface;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;

class AboutSectionsRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ListDataTableInterface $listDataTable,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct($registry, AboutSections::class);
    }

    public function getList(Domains $domain, bool $isCount = false): ?array
    {
        $this->listDataTable->setRealColumns([
            'about.active',
            'about.title',
            'about.position',
            'about.id',
        ]);
        $query = $this->createQueryBuilder('about');

        if ($isCount) {
            $query->select('COUNT(about.id) as total');
        } else {
            $query->select($this->listDataTable->getRealColumns());
        }

        $query
            ->andWhere('about.domain = :domainId')
            ->setParameter('domainId', UUIDUtil::convertIdToSearch($domain));

        return $this->listDataTable->complementQueryResult($query, $isCount);
    }

    public function getPublicActiveList(Domains $domain): array
    {
        return $this->createQueryBuilder('about')
            ->select(['about.id', 'about.title', 'about.text', 'about.image', 'about.position'])
            ->where('about.domain = :domain')
            ->andWhere('about.active = :active')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->setParameter('active', true)
            ->orderBy('about.position', 'ASC')
            ->addOrderBy('about.createdAt', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function getNextPosition(Domains $domain): int
    {
        $max = $this->createQueryBuilder('about')
            ->select('MAX(about.position)')
            ->where('about.domain = :domain')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->getQuery()
            ->getSingleScalarResult();

        return $max === null ? 1 : ((int) $max) + 1;
    }

    public function invalidatePublicCache(Domains $domain): void
    {
        $this->cache->delete('store_about_' . $domain->getId());
    }
}
