<?php

namespace App\Handler\UseCase\Store\Products;

use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Products\GetPublicCategoriesInterface;
use App\Interface\UseCase\Store\Products\GetPublicHomeInterface;
use App\Interface\UseCase\Store\Products\GetPublicSlidesInterface;
use App\Repository\Products\ProductsRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class GetPublicHomeUseCase implements GetPublicHomeInterface
{
    public function __construct(
        private readonly GetDomainDataInterface $getDomainData,
        private readonly GetPublicSlidesInterface $getSlides,
        private readonly GetPublicCategoriesInterface $getCategories,
        private readonly ProductsRepository $productsRepository,
        private readonly CacheInterface $cache,
        private readonly LogInterface $log,
    ) {}

    public function handler(): array
    {
        try {
            $domain = $this->getDomainData->getDomainCache();
            return $this->cache->get(
                "store_home_{$domain->getId()}",
                function (ItemInterface $item) use ($domain): array {
                    $item->expiresAfter(900);

                    $products = $this->productsRepository->getPublicHomeList($domain, 8);

                    return [
                        'slides'     => $this->getSlides->handler(),
                        'categories' => $this->getCategories->handler(),
                        'products'   => $products,
                    ];
                }
            );
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['slides' => [], 'categories' => [], 'products' => []];
        }
    }
}
