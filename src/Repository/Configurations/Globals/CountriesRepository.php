<?php

namespace App\Repository\Configurations\Globals;

use App\Entity\Configurations\Globals\Countries;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CountriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly CacheInterface $cache)
    {
        parent::__construct($registry, Countries::class);
    }

    public function findAllActive(bool $useCache = true): array
    {
        if (!$useCache) {
            return $this->findBy(['active' => true]);
        }

        return $this->cache->get(
            'countries_all_active',
            function (ItemInterface $item): array {
                $item->expiresAfter(86400);
                return $this->findBy(['active' => true]);
            }
        );
    }

    public function invalidateAllActiveCache(): void
    {
        $this->cache->delete('countries_all_active');
    }
}
