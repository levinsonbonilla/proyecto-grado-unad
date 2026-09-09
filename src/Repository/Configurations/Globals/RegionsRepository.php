<?php

namespace App\Repository\Configurations\Globals;

use App\Entity\Configurations\Globals\Regions;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RegionsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Regions::class);
    }

    public function findActiveByCountryIds(array $countryIds): array
    {
        $binaryIds = array_map(fn(string $id) => UUIDUtil::convertIdToSearch($id), $countryIds);

        return $this->createQueryBuilder('r')
            ->join('r.country', 'c')
            ->where('r.active = :active')
            ->andWhere('c.id IN (:ids)')
            ->setParameter('active', true)
            ->setParameter('ids', $binaryIds)
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
