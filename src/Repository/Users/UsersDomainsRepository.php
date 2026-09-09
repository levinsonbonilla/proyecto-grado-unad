<?php

namespace App\Repository\Users;

use App\Entity\Users\UsersDomains;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\ListDataTableInterface;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UsersDomainsRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ListDataTableInterface $listDataTable,
        private readonly GetDomainDataInterface $getDomainData
    ) {
        parent::__construct($registry, UsersDomains::class);
    }

    public function getUserList(bool $isCount = false): ?array
    {
        $this->listDataTable->setRealColumns([
            "user.name",
            "user.email",
            "user.phone",
            "userDomain.roles",
            "user.active",
        ]);
        $query = $this->createQueryBuilder('userDomain');

        if ($isCount) {
            $query->select("COUNT(user.id) as total");
        } else {
            $query->select(
                "
                CONCAT(user.name,' ',user.lastName) as name,
                user.email,
                CONCAT('(+',COALESCE(user.prefix, '0'),') ', COALESCE(user.phone, '0000000000')) as phone,
                userDomain.roles,
                user.active,
                user.id,
                domain.domain"
            );
        }

        $query
            ->join("userDomain.user", "user")
            ->join("userDomain.domain", "domain")
            ->andWhere('domain.id = :domain')
            ->setParameter('domain', UUIDUtil::convertIdToSearch($this->getDomainData->getDomainCache()));

        $query = $this->listDataTable->searchByAllColumns(
            $query,
            ["user.lastName"]
        );

        $query = $this->listDataTable->preGetQuery($query, $isCount);

        if ($isCount) {
            return $query->getQuery()->getSingleResult();
        }

        return $query->getQuery()->getResult();
    }
}
