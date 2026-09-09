<?php

namespace App\Handler\UseCase\General;

use App\Entity\Products\Products;
use App\Interface\UseCase\General\GetProductInterface;
use App\Repository\Products\Colors\ProductsColorsRepository;
use App\Repository\Products\ProductsRepository;
use App\Service\Products\FavoriteProductIdsResolverInterface;
use App\Service\Products\GalleryImageGrouperInterface;

final class GetProductUseCase implements GetProductInterface
{
    public function __construct(
        private readonly ProductsRepository $productsRepository,
        private readonly FavoriteProductIdsResolverInterface $favoriteProductIds,
        private readonly ProductsColorsRepository $productsColorsRepository,
        private readonly GalleryImageGrouperInterface $galleryImageGrouper,
    ) {}
    public function handler(Products $product): array
    {
        $favoriteIds = $this->favoriteProductIds->resolve();

        return [
            'id'          => $product->getId(),
            'name'        => $product->getName(),
            'description' => $product->getDescription(),
            'publicPrice' => $product->getPublicPrice(),

            'images'      => $this->galleryImageGrouper->group(
                $this->productsRepository->getProductToDetailInECommerce($product)
            ),

            'isFavorite'  => in_array((string) $product->getId(), $favoriteIds, true),
            'stock'       => $product->getStock(),

            'colors'      => $this->productsColorsRepository->getActiveByProduct($product),
        ];
    }
}
