<?php

namespace App\Repository\Tenants\Domains;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Interface\Configuration\ListDataTableInterface;
use App\Util\UUIDUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PaymentMethodsRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ListDataTableInterface $listDataTable,
        private readonly CacheInterface $cache
    ) {
        parent::__construct($registry, PaymentMethods::class);
    }

    public function getList(Domains $domain, bool $isCount = false): ?array
    {
        $this->listDataTable->setRealColumns([
            "pm.active",
            "pm.name",
            "pm.id",
        ]);
        $query = $this->createQueryBuilder('pm');
        if ($isCount) {
            $query->select("COUNT(pm.id) as total");
        } else {
            $query->select($this->listDataTable->getRealColumns());
        }
        $query->andWhere("pm.domain = :domainId")
              ->setParameter("domainId", UUIDUtil::convertIdToSearch($domain));
        return $this->listDataTable->complementQueryResult($query, $isCount);
    }

    public function getOneByIdCache(Uuid $id): ?PaymentMethods
    {
        return $this->cache->get("payment_method_{$id}", function (ItemInterface $item) use ($id) {
            $item->expiresAfter(86400);
            return $this->findOneBy(['id' => $id, 'active' => true]);
        });
    }

    public function invalidateCache(Uuid $id): void
    {
        $this->cache->delete("payment_method_{$id}");
    }
}
