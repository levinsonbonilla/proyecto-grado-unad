<?php

namespace App\Repository\Configurations\Globals;

use App\Entity\Configurations\Globals\Cities;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CitiesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cities::class);
    }

    public function findActiveByRegionIds(array $regionIds): array
    {
        $binaryIds = array_map(fn(string $id) => UUIDUtil::convertIdToSearch($id), $regionIds);

        return $this->createQueryBuilder('c')
            ->join('c.region', 'r')
            ->where('c.active = :active')
            ->andWhere('r.id IN (:ids)')
            ->setParameter('active', true)
            ->setParameter('ids', $binaryIds)
            ->orderBy('c.names', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
