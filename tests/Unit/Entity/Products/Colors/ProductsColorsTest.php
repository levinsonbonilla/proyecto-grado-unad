<?php

namespace App\Tests\Unit\Entity\Products\Colors;

use App\ArgumentHandler\ProductColorArgument;
use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Products;
use PHPUnit\Framework\TestCase;

class ProductsColorsTest extends TestCase
{
    private function buildArgument(array $overrides = []): ProductColorArgument
    {
        return new ProductColorArgument(array_merge([
            'stock' => 5,
        ], $overrides), new Products());
    }

    public function testNewBlockHasNotSentLowStockAlert(): void
    {
        $block = new ProductsColors();
        $block->add($this->buildArgument());

        $this->assertFalse($block->hasLowStockAlertSent());
    }

    public function testMarkLowStockAlertSentSetsFlag(): void
    {
        $block = new ProductsColors();
        $block->add($this->buildArgument());

        $block->markLowStockAlertSent();

        $this->assertTrue($block->hasLowStockAlertSent());
    }

    public function testEditResetsLowStockAlertFlag(): void
    {
        $block = new ProductsColors();
        $block->add($this->buildArgument(['stock' => 1]));
        $block->markLowStockAlertSent();
        $this->assertTrue($block->hasLowStockAlertSent());

        $block->edit($this->buildArgument(['stock' => 1]));

        $this->assertFalse($block->hasLowStockAlertSent());
    }
}
