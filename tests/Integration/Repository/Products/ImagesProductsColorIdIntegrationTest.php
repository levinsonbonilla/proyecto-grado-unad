<?php

namespace App\Tests\Integration\Repository\Products;

use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Products;
use App\Repository\Products\Others\ImagesProductsRepository;
use App\Repository\Products\ProductsRepository;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\HttpFoundation\Request;

class ImagesProductsColorIdIntegrationTest extends IntegrationTestCase
{
    private function productoTestA(): Products
    {
        return $this->em->getRepository(Products::class)->findOneBy(['name' => 'Producto Test A']);
    }

    private function pushRequestForDomain(string $domain): void
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create($domain . '/es/'));
    }

    public function testImagesProductsRepositoryReturnsColorIdMatchingTheBlock(): void
    {
        $product = $this->productoTestA();
        $negroBlock = $this->em->getRepository(ProductsColors::class)->findOneBy(['product' => $product, 'stock' => 5]);

        $repository = static::getContainer()->get(ImagesProductsRepository::class);
        $images = $repository->getActiveByProductOrdered($product);

        $this->assertNotEmpty($images);
        $withColor = array_values(array_filter($images, fn ($img) => $img['colorId'] !== null));
        $this->assertCount(1, $withColor);

        $this->assertSame((string) $negroBlock->getId(), (string) $withColor[0]['colorId']);
    }

    public function testProductsRepositoryGetProductToDetailReturnsSameColorId(): void
    {
        $this->pushRequestForDomain('http://localhost:8060');

        $product = $this->productoTestA();
        $negroBlock = $this->em->getRepository(ProductsColors::class)->findOneBy(['product' => $product, 'stock' => 5]);

        $repository = static::getContainer()->get(ProductsRepository::class);
        $images = $repository->getProductToDetailInECommerce($product);

        $this->assertNotEmpty($images);
        $withColor = array_values(array_filter($images, fn ($img) => $img['colorId'] !== null));
        $this->assertCount(1, $withColor);
        $this->assertSame((string) $negroBlock->getId(), (string) $withColor[0]['colorId']);
    }
}
