<?php

namespace App\Repository\Products\Medidas;

use App\Entity\Products\Medidas\Medidas;
use App\Entity\Tenants\Domains\Domains;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\ListDataTableInterface;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MedidasRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ListDataTableInterface $listDataTable,
        private readonly GetDomainDataInterface $getDomainData,
    ) {
        parent::__construct($registry, Medidas::class);
    }

    public function getMedidasList(bool $isCount = false): ?array
    {
        $this->listDataTable->setRealColumns([
            "medidas.id",
            "medidas.name",
            "medidas.active",
        ]);
        $query = $this->createQueryBuilder('medidas');

        if ($isCount) {
            $query->select("COUNT(medidas.id) as total");
        } else {
            $query->select($this->listDataTable->getRealColumns());
        }

        $query
            ->join("medidas.domain", "domain")
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
        return $this->createQueryBuilder('medidas')
            ->where('medidas.domain = :domain')
            ->andWhere('medidas.active = :active')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->setParameter('active', true)
            ->orderBy('medidas.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByDomainAndName(Domains $domain, string $name): ?Medidas
    {
        return $this->createQueryBuilder('medidas')
            ->where('medidas.domain = :domain')
            ->andWhere('LOWER(medidas.name) = LOWER(:name)')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($domain))
            ->setParameter('name', trim($name))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
