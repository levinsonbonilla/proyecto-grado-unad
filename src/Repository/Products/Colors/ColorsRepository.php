<?php

namespace App\Repository\Products\Colors;

use App\Entity\Products\Colors\Colors;
use App\Entity\Tenants\Domains\Domains;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\ListDataTableInterface;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ColorsRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ListDataTableInterface $listDataTable,
        private readonly GetDomainDataInterface $getDomainData,
    ) {
        parent::__construct($registry, Colors::class);
    }

    public function getColorsList(bool $isCount = false): ?array
    {
        $this->listDataTable->setRealColumns([
            "colors.id",
            "colors.name",
            "colors.hexCode",
            "colors.active",
        ]);
        $query = $this->createQueryBuilder('colors');

        if ($isCount) {
            $query->select("COUNT(colors.id) as total");
        } else {
            $query->select($this->listDataTable->getRealColumns());
        }

        $query
            ->join("colors.domain", "domain")
            ->andWhere('domain.id = :domain')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($this->getDomainData->getDomainCache()));

        $query = $this->listDataTable->searchByAllColumns($query);
        $query = $this->listDataTable->preGetQuery($query, $isCount);

        if ($isCount) {
            return $query->getQuery()->getSingleResult();
        }

        return $query->getQuery()->getResult();
    }

    public function getActiveByDomain(Domains $domain): array
    {
        return $this->createQueryBuilder('colors')
            ->where('colors.domain = :domain')
            ->andWhere('colors.active = :active')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->setParameter('active', true)
            ->orderBy('colors.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByDomainAndName(Domains $domain, string $name): ?Colors
    {
        return $this->createQueryBuilder('colors')
            ->where('colors.domain = :domain')
            ->andWhere('LOWER(colors.name) = LOWER(:name)')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->setParameter('name', trim($name))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
