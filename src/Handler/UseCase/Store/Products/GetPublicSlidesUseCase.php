<?php

namespace App\Handler\UseCase\Store\Products;

use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Products\GetPublicSlidesInterface;
use App\Repository\Tenants\Others\SlidesRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class GetPublicSlidesUseCase implements GetPublicSlidesInterface
{
    public function __construct(
        private readonly GetDomainDataInterface $getDomainData,
        private readonly SlidesRepository $slidesRepository,
        private readonly CacheInterface $cache,
        private readonly LogInterface $log,
    ) {}

    public function handler(): array
    {
        try {
            $domain = $this->getDomainData->getDomainCache();
            return $this->cache->get(
                "store_slides_{$domain->getId()}",
                function (ItemInterface $item) use ($domain): array {
                    $item->expiresAfter(3600);
                    return $this->slidesRepository->getPublicActiveList($domain);
                }
            );
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return [];
        }
    }
}
