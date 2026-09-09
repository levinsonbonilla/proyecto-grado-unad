<?php

namespace App\Tests\Unit\Entity\Products\Others;

use App\ArgumentHandler\ImageProductArgument;
use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Others\ImagesProducts;
use App\Entity\Products\Products;
use PHPUnit\Framework\TestCase;

class ImagesProductsTest extends TestCase
{
    private function buildArgument(array $overrides = []): ImageProductArgument
    {
        return new ImageProductArgument(array_merge([
            'image'       => 'http://localhost/nueva.jpg',
            'description' => 'Nueva descripción',
            'orderColumn' => 2,
        ], $overrides), new Products());
    }

    public function testEditUpdatesImageDescriptionAndOrder(): void
    {
        $image = new ImagesProducts();
        $image->add($this->buildArgument([
            'image' => 'http://localhost/original.jpg',
            'description' => 'Original',
            'orderColumn' => 1,
        ]));

        $image->edit($this->buildArgument([
            'image' => 'http://localhost/actualizada.jpg',
            'description' => 'Actualizada',
            'orderColumn' => 5,
        ]));

        $this->assertSame('http://localhost/actualizada.jpg', $image->getImage());
        $this->assertSame('Actualizada', $image->getDescription());
        $this->assertSame(5, $image->getOrderColumn());
    }

    public function testEditDoesNotChangeProduct(): void
    {
        $originalProduct = new Products();
        $image = new ImagesProducts();
        $image->add(new ImageProductArgument([
            'image' => 'http://localhost/original.jpg',
            'description' => null,
            'orderColumn' => 1,
        ], $originalProduct));

        $editArgument = new ImageProductArgument([
            'image' => 'http://localhost/otra.jpg',
            'description' => null,
            'orderColumn' => 2,
        ], new Products());
        $image->edit($editArgument);

        $this->assertSame($originalProduct, $image->getProduct());
    }

    public function testAddSetsProductColorWhenProvided(): void
    {
        $block = $this->createMock(ProductsColors::class);
        $image = new ImagesProducts();
        $image->add($this->buildArgument(['productColor' => $block]));

        $this->assertSame($block, $image->getProductColor());
    }

    public function testAddWithoutProductColorLeavesItNull(): void
    {
        $image = new ImagesProducts();
        $image->add($this->buildArgument());

        $this->assertNull($image->getProductColor());
    }

    public function testEditUpdatesProductColor(): void
    {
        $block = $this->createMock(ProductsColors::class);
        $image = new ImagesProducts();
        $image->add($this->buildArgument());

        $image->edit($this->buildArgument(['productColor' => $block]));

        $this->assertSame($block, $image->getProductColor());
    }
}
