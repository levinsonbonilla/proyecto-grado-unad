<?php

namespace App\Repository\Tenants\Domains;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Interface\Configuration\ListDataTableInterface;
use App\Util\StringUtil;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\ItemInterface;

class DomainsRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly CacheItemPoolInterface $cache,
        private readonly ListDataTableInterface $listDataTable
    ) {
        parent::__construct($registry,  Domains::class);
    }

    public function getDomain(string $domain): ?Domains
    {
        return $this->createQueryBuilder('d')
            ->andWhere("d.domain LIKE :domain")
            ->andWhere('d.active = true')

            ->setParameter('domain', '%' . $domain)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getDomainCache(string $domain): ?Domains
    {
        $complementName = StringUtil::removeProhibitedCharactersForCache($domain);
        return $this->cache->get("domain_$complementName", function (ItemInterface $item) use ($domain) {

            $item->expiresAfter(86400);
            return $this->createQueryBuilder('d')
                ->andWhere("d.domain LIKE :domain")
                ->andWhere('d.active = true')

                ->setParameter('domain', '%' . $domain)
                ->getQuery()
                ->getOneOrNullResult();
        });
    }

    public function invalidateCacheDomain(string $domain): void
    {
        $complementName = StringUtil::removeProhibitedCharactersForCache($domain);
        $this->cache->deleteItem("domain_$complementName");
    }

    public function getActiveDomainsByTenant(Tenants $tenant): array
    {
        $complementName = StringUtil::removeProhibitedCharactersForCache($tenant->getId());
        return $this->cache->get("active_domains_by_tenant_$complementName", function (ItemInterface $item) use ($tenant) {
            $item->expiresAfter(86400);
            return $this->createQueryBuilder('d')
                ->andWhere('d.tenant = :tenant')
                ->andWhere('d.active = true')
                ->setParameter('tenant', UUIDUtil::convertIdToSearch($tenant))
                ->orderBy('d.domain', 'ASC')
                ->getQuery()
                ->getResult();
        });
    }

    public function invalidateActiveDomainsByTenantCache(Tenants $tenant): void
    {
        $complementName = StringUtil::removeProhibitedCharactersForCache($tenant->getId());
        $this->cache->deleteItem("active_domains_by_tenant_$complementName");
    }

    public function getDomainsByTenant(Tenants $tenant): array
    {
        $complementName = StringUtil::removeProhibitedCharactersForCache($tenant->getId());
        return $this->cache->get("domains_by_tenant_$complementName", function (ItemInterface $item) use ($tenant) {

            $item->expiresAfter(86400);
            return $this->createQueryBuilder('d')
                ->select("d.id, d.domain")
                ->andWhere("d.tenant = :tenant")
                ->andWhere('d.active = true')
                ->setParameter('tenant', UUIDUtil::convertIdToSearch($tenant))
                ->getQuery()
                ->getResult();
        });
    }

    public function getDomainsList(?Tenants $tenant, bool $isCount = false): ?array
    {
        $columns = $tenant === null
            ? ["domain.active", "tenant.name as tenantName", "domain.name as domainName", "domain.domain", "domain.notificationEmail", "domain.supportEmail", "domain.id"]
            : ["domain.active", "domain.name as domainName", "domain.domain", "domain.notificationEmail", "domain.supportEmail", "domain.id"];
        $this->listDataTable->setRealColumns($columns);
        $query = $this->createQueryBuilder('domain');

        if ($isCount) {
            $query->select("COUNT(domain.id) as total");
        } else {
            $query->select($this->listDataTable->getRealColumns());
        }

        if ($tenant === null) {
            $query->join("domain.tenant", "tenant");
        } else {
            $query
                ->join("domain.tenant", "tenant")
                ->andWhere("tenant.id = :tenantId")
                ->setParameter("tenantId", UUIDUtil::convertIdToSearch($tenant));
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
