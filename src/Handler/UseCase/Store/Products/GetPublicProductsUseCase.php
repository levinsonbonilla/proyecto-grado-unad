<?php

namespace App\Handler\UseCase\Store\Products;

use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Store\Products\GetPublicProductsInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\ProductsRepository;
use App\Service\Products\FavoriteProductIdsResolverInterface;

final class GetPublicProductsUseCase implements GetPublicProductsInterface
{
    public function __construct(
        private readonly GetDomainDataInterface $getDomainData,
        private readonly ProductsRepository $productsRepository,
        private readonly FavoriteProductIdsResolverInterface $favoriteProductIds,
        private readonly LogInterface $log,
    ) {}

    public function handler(
        ?string $categoryId = null,
        ?string $search     = null,
        ?int    $minPrice   = null,
        ?int    $maxPrice   = null,
        int     $page       = 1,
        int     $limit      = 12,
    ): array {
        try {
            $domain = $this->getDomainData->getDomainCache();

            $result = $this->productsRepository->searchPublic(
                domain: $domain,
                allowedProductIds: null,
                categoryId: $categoryId,
                search: $search,
                minPrice: $minPrice,
                maxPrice: $maxPrice,
                page: $page,
                limit: $limit,
            );

            $favoriteIds = $this->favoriteProductIds->resolve();
            $items = array_map(function (array $item) use ($favoriteIds): array {
                $item['isFavorite'] = in_array((string) $item['id'], $favoriteIds, true);
                return $item;
            }, $result['items']);

            return [
                'items'       => $items,
                'total'       => $result['total'],
                'page'        => $page,
                'limit'       => $limit,
                'totalPages'  => (int) ceil($result['total'] / $limit),
            ];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['items' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'totalPages' => 0];
        }
    }
}
