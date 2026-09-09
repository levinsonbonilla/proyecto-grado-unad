<?php

namespace App\Handler\UseCase\Store\Products;

use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Products\GetPublicCategoriesInterface;
use App\Repository\Products\Categories\CategoriesRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class GetPublicCategoriesUseCase implements GetPublicCategoriesInterface
{
    public function __construct(
        private readonly GetDomainDataInterface $getDomainData,
        private readonly CategoriesRepository $categoriesRepository,
        private readonly CacheInterface $cache,
        private readonly LogInterface $log,
    ) {}

    public function handler(): array
    {
        try {
            $domain = $this->getDomainData->getDomainCache();
            return $this->cache->get(
                "store_categories_{$domain->getId()}",
                function (ItemInterface $item) use ($domain): array {
                    $item->expiresAfter(3600);
                    return $this->categoriesRepository->getPublicActiveList($domain);
                }
            );
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return [];
        }
    }
}
