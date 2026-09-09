<?php

namespace App\Repository\Tenants;

use App\Entity\Tenants\Tenants;
use App\Interface\Configuration\ListDataTableInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Uid\Uuid;



class TenantsRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly CacheItemPoolInterface $cache,
        private readonly ListDataTableInterface $listDataTable
    ) {
        parent::__construct($registry, Tenants::class);
    }

    public function getTenantCache(Uuid $tenantId): ?Tenants
    {
        $complementName = $tenantId->__toString();
        return $this->cache->get("tenant_$complementName", function (ItemInterface $item) use ($tenantId) {
            
            $item->expiresAfter(86400);
            return $this->findOneBy(["id" => $tenantId, "active" => true]);
        });
    }

    public function invalidateCacheTenantId(Uuid $tenantId): void
    {
        $complementName = $tenantId->__toString();
        $this->cache->deleteItem("tenant_$complementName");
    }

    public function getTenant(Uuid $tenantId): ?Tenants
    {
        return $this->findOneBy(["id" => $tenantId, "active" => true]);
    }

    public function getTenantList(bool $isCount = false): ?array
    {
        $this->listDataTable->setRealColumns([
            "tenant.active",
            "tenant.name",
            "tenant.nit",
            "tenant.phone",
            "tenant.description",
            "tenant.id",
        ]);
        $query = $this->createQueryBuilder('tenant');

        
        if ($isCount) {
            $query->select("COUNT(tenant.id) as total");
        } else {
            $phone = "CONCAT('(+',COALESCE(tenant.prefix, '0'),') ', COALESCE(tenant.phone, '0000000000')) as phone";
            
            
            
            $primaryDomain = '(SELECT MIN(d.domain) FROM App\Entity\Tenants\Domains\Domains d
                WHERE d.tenant = tenant.id AND d.active = true) as primaryDomain';
            $columns = array_merge($this->listDataTable->getRealColumns(["3" => $phone]), [$primaryDomain]);
            $query->select($columns);
        }

        $query = $this->listDataTable->searchByAllColumns(
            $query
        );

        $query = $this->listDataTable->preGetQuery($query, $isCount);

        
        if ($isCount) {
            return $query->getQuery()->getSingleResult();
        }

        return $query->getQuery()->getResult();
    }
}
