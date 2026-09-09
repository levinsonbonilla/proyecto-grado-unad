<?php

namespace App\Handler\UseCase\Store\Products;

use App\Entity\Products\Products;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Products\GetPublicProductDetailInterface;
use App\Repository\Products\Colors\ProductsColorsRepository;
use App\Repository\Products\Others\ImagesProductsRepository;
use App\Service\Products\GalleryImageGrouperInterface;

final class GetPublicProductDetailUseCase implements GetPublicProductDetailInterface
{
    public function __construct(
        private readonly ImagesProductsRepository $imagesProductsRepository,
        private readonly ProductsColorsRepository $productsColorsRepository,
        private readonly LogInterface $log,
        private readonly GalleryImageGrouperInterface $galleryImageGrouper,
    ) {}

    public function handler(Products $product): array
    {
        try {

            $images     = $this->galleryImageGrouper->group(
                $this->imagesProductsRepository->getActiveByProductOrdered($product)
            );
            $colors     = $this->productsColorsRepository->getActiveByProduct($product);

            return [
                'product'   => $product,
                'images'    => $images,

                'showStock' => true,
                'colors'    => $colors,
            ];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return [
                'product' => $product, 'images' => [],
                'showStock' => false, 'colors' => [],
            ];
        }
    }
}
