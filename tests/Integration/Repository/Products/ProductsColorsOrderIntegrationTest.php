<?php

namespace App\Tests\Integration\Repository\Products;

use App\Entity\Products\Products;
use App\Repository\Products\Colors\ProductsColorsRepository;
use App\Repository\Products\Others\ImagesProductsRepository;
use App\Repository\Products\ProductsRepository;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\HttpFoundation\Request;

class ProductsColorsOrderIntegrationTest extends IntegrationTestCase
{
    private function pushRequestForDomain(string $domain): void
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create($domain . '/es/'));
    }

    public function testGetActiveByProductOrdersByBlockOrderColumnBeforeAlphabetical(): void
    {
        $product = $this->em->getRepository(Products::class)->findOneBy(['name' => 'Producto Test A']);

        $repository = static::getContainer()->get(ProductsColorsRepository::class);
        $rows = $repository->getActiveByProduct($product);

        $colorNames = array_values(array_map(
            fn ($r) => $r['colorName'],
            array_filter($rows, fn ($r) => $r['colorName'] !== null && $r['medidaName'] === null)
        ));
        $this->assertSame(['Azul', 'Rojo', 'Negro'], $colorNames);
    }

    public function testGetActiveByProductBlockOrderColumnIsHydrated(): void
    {
        $product = $this->em->getRepository(Products::class)->findOneBy(['name' => 'Producto Test A']);

        $repository = static::getContainer()->get(ProductsColorsRepository::class);
        $rows = $repository->getActiveByProduct($product);

        $rojo = current(array_filter($rows, fn ($r) => $r['colorName'] === 'Rojo'));
        $azul = current(array_filter($rows, fn ($r) => $r['colorName'] === 'Azul'));

        $this->assertSame(1, $rojo['orderColumn']);
        $this->assertNull($azul['orderColumn']);
    }

    public function testImagesProductsRepositoryGroupsGalleryByBlockOrderBeforePhotoOwnOrder(): void
    {
        $product = $this->em->getRepository(Products::class)->findOneBy(['name' => 'Producto Test C (con stock)']);

        $repository = static::getContainer()->get(ImagesProductsRepository::class);
        $images = $repository->getActiveByProductOrdered($product);

        $this->assertCount(2, $images);

        $this->assertSame('https://example.com/orden-rojo.jpg', $images[0]['image']);
        $this->assertSame('https://example.com/orden-negro.jpg', $images[1]['image']);
    }

    public function testProductsRepositoryGetProductToDetailGroupsGalleryByBlockOrderBeforePhotoOwnOrder(): void
    {
        $this->pushRequestForDomain('http://localhost:8060');

        $product = $this->em->getRepository(Products::class)->findOneBy(['name' => 'Producto Test C (con stock)']);

        $repository = static::getContainer()->get(ProductsRepository::class);
        $images = $repository->getProductToDetailInECommerce($product);

        $this->assertCount(2, $images);
        $this->assertSame('https://example.com/orden-rojo.jpg', $images[0]['image']);
        $this->assertSame('https://example.com/orden-negro.jpg', $images[1]['image']);
    }
}
