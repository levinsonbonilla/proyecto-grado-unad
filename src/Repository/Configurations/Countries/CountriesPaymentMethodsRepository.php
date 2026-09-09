<?php

namespace App\Repository\Configurations\Countries;

use App\Entity\Configurations\Countries\CountriesPaymentMethods;
use App\Entity\Tenants\Domains\PaymentMethods;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CountriesPaymentMethodsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly CacheInterface $cache)
    {
        parent::__construct($registry, CountriesPaymentMethods::class);
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
                ->join('cc.country', 'c')
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
            "payment_methods_country_ids_{$entity->getId()}",
            function (ItemInterface $item) use ($fetch) {
                $item->expiresAfter(86400);
                return $fetch();
            }
        );
    }

    public function invalidateSelectedIdsCache(PaymentMethods $entity): void
    {
        $this->cache->delete("payment_methods_country_ids_{$entity->getId()}");
    }
}
