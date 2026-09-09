<?php

namespace App\Tests\Integration\Repository\Products;

use App\Entity\Products\Medidas\Medidas;
use App\Entity\Products\Products;
use App\Repository\Products\Colors\ProductsColorsRepository;
use App\Repository\Products\Others\ImagesProductsRepository;
use App\Service\Products\GalleryImageGrouperService;
use App\Tests\Integration\IntegrationTestCase;

class ProductsColorsGetActiveByProductMedidaIntegrationTest extends IntegrationTestCase
{
    private function productoTestA(): Products
    {
        return $this->em->getRepository(Products::class)->findOneBy(['name' => 'Producto Test A']);
    }

    public function testReturnsAllEligibleBlocksIncludingMedidaOnlyOnes(): void
    {
        $product = $this->productoTestA();

        $repository = static::getContainer()->get(ProductsColorsRepository::class);
        $rows = $repository->getActiveByProduct($product);

        $this->assertCount(6, $rows);
    }

    public function testHydratesMedidaIdAndMedidaNameForColorAndMedidaBlock(): void
    {
        $product = $this->productoTestA();
        $medidaM = $this->em->getRepository(Medidas::class)->findOneBy(['name' => 'M']);

        $repository = static::getContainer()->get(ProductsColorsRepository::class);
        $rows = $repository->getActiveByProduct($product);

        $negroM = array_values(array_filter(
            $rows,
            fn ($row) => $row['colorName'] === 'Negro' && $row['medidaName'] === 'M'
        ));

        $this->assertCount(1, $negroM);

        $this->assertSame((string) $medidaM->getId(), (string) $negroM[0]['medidaId']);
        $this->assertNotNull($negroM[0]['colorId']);
    }

    public function testReturnsNullColorIdForMedidaOnlyBlock(): void
    {
        $product = $this->productoTestA();

        $repository = static::getContainer()->get(ProductsColorsRepository::class);
        $rows = $repository->getActiveByProduct($product);

        $medidaOnly = array_values(array_filter(
            $rows,
            fn ($row) => $row['colorName'] === null && $row['medidaName'] === 'M'
        ));

        $this->assertCount(1, $medidaOnly);
        $this->assertNull($medidaOnly[0]['colorId']);
        $this->assertNotNull($medidaOnly[0]['medidaId']);
    }

    public function testDistinguishesTwoBlocksOfSameColorByDifferentMedida(): void
    {
        $product = $this->productoTestA();

        $repository = static::getContainer()->get(ProductsColorsRepository::class);
        $rows = $repository->getActiveByProduct($product);

        $negroBlocks = array_values(array_filter($rows, fn ($row) => $row['colorName'] === 'Negro'));

        $this->assertCount(3, $negroBlocks);

        $medidaNames = array_map(fn ($row) => $row['medidaName'], $negroBlocks);
        sort($medidaNames);
        $this->assertSame([null, 'L', 'M'], $medidaNames);
    }

    public function testReturnsOneRowPerMedidaForColorWithMultipleMedidas(): void
    {
        $product = $this->em->getRepository(Products::class)->findOneBy(['name' => 'Producto Test B']);

        $repository = static::getContainer()->get(ProductsColorsRepository::class);
        $rows = $repository->getActiveByProduct($product);

        $rosaBlocks = array_values(array_filter($rows, fn ($row) => $row['colorName'] === 'Rosa'));
        $this->assertCount(3, $rosaBlocks);

        $medidaNames = array_map(fn ($row) => $row['medidaName'], $rosaBlocks);
        sort($medidaNames);
        $this->assertSame(['L', 'M', 'S'], $medidaNames);
    }

    public function testEachMedidaRowHasItsOwnCopyOfTheSharedPhotos(): void
    {
        $product = $this->em->getRepository(Products::class)->findOneBy(['name' => 'Producto Test B']);

        $colorsRepository = static::getContainer()->get(ProductsColorsRepository::class);
        $rosaBlocks = array_values(array_filter(
            $colorsRepository->getActiveByProduct($product),
            fn ($row) => $row['colorName'] === 'Rosa'
        ));

        $imagesRepository = static::getContainer()->get(ImagesProductsRepository::class);
        $images = $imagesRepository->getActiveByProductOrdered($product);

        foreach ($rosaBlocks as $block) {
            $photosForThisBlock = array_values(array_filter(
                $images,
                fn ($img) => (string) $img['colorId'] === (string) $block['id']
            ));
            $this->assertCount(2, $photosForThisBlock, 'cada fila de medida debe tener sus propias 2 fotos');
        }
    }

    public function testGalleryImageGrouperCollapsesSharedPhotosOfMultiMedidaBlockToDistinctPhotosOnly(): void
    {
        $product = $this->em->getRepository(Products::class)->findOneBy(['name' => 'Producto Test B']);

        $colorsRepository = static::getContainer()->get(ProductsColorsRepository::class);
        $rosaBlockIds = array_map(
            fn ($row) => (string) $row['id'],
            array_values(array_filter(
                $colorsRepository->getActiveByProduct($product),
                fn ($row) => $row['colorName'] === 'Rosa'
            ))
        );
        sort($rosaBlockIds);

        $imagesRepository = static::getContainer()->get(ImagesProductsRepository::class);
        $images = $imagesRepository->getActiveByProductOrdered($product);

        $grouped = (new GalleryImageGrouperService())->group($images);
        $rosaGrouped = array_values(array_filter(
            $grouped,
            fn ($row) => array_intersect(explode(',', $row['colorIds']), $rosaBlockIds) !== []
        ));

        $this->assertCount(2, $rosaGrouped, 'las 6 filas físicas de "Rosa" deben colapsar a sus 2 fotos distintas');
        foreach ($rosaGrouped as $row) {
            $ids = explode(',', $row['colorIds']);
            sort($ids);
            $this->assertSame($rosaBlockIds, $ids, 'cada foto agrupada debe listar los 3 bloques de medida que la comparten');
        }
    }
}
