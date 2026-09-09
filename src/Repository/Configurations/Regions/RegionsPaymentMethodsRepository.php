<?php

namespace App\Repository\Configurations\Regions;

use App\Entity\Configurations\Regions\RegionsPaymentMethods;
use App\Entity\Tenants\Domains\PaymentMethods;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class RegionsPaymentMethodsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly CacheInterface $cache)
    {
        parent::__construct($registry, RegionsPaymentMethods::class);
    }

    public function findAllByPaymentMethod(PaymentMethods $entity): array
    {
        return $this->createQueryBuilder('cc')
            ->where('IDENTITY(cc.paymentMethods) = :entityId')
            ->setParameter('entityId', $entity->getId(), 'uuid')
            ->getQuery()
            ->getResult();
    }

    public function getSelectedIds(PaymentMethods $entity, bool $useCache = true): array
    {

        $fetch = function () use ($entity): array {
            $rows = $this->createQueryBuilder('cc')
                ->join('cc.region', 'c')
                ->select('c.id')
                ->where('IDENTITY(cc.paymentMethods) = :entityId')
                ->andWhere('cc.active = :active')
                ->setParameter('entityId', $entity->getId(), 'uuid')
                ->setParameter('active', true)
                ->getQuery()
                ->getArrayResult();

            return array_map(fn (array $row) => (string) $row['id'], $rows);
        };

        if (!$useCache) {
            return $fetch();
        }

        return $this->cache->get(
            "payment_methods_region_ids_{$entity->getId()}",
            function (ItemInterface $item) use ($fetch) {
                $item->expiresAfter(86400);
                return $fetch();
            }
        );
    }

    public function invalidateSelectedIdsCache(PaymentMethods $entity): void
    {
        $this->cache->delete("payment_methods_region_ids_{$entity->getId()}");
    }
}
