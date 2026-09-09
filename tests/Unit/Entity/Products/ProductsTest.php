<?php

namespace App\Tests\Unit\Entity\Products;

use App\ArgumentHandler\ProductsArgument;
use App\Entity\Products\Products;
use App\Entity\Tenants\Domains\Domains;
use PHPUnit\Framework\TestCase;

class ProductsTest extends TestCase
{
    private function buildArgument(array $overrides = []): ProductsArgument
    {
        return new ProductsArgument(array_merge([
            'name'         => 'Producto X',
            'description'  => 'Descripción',
            'basePrice'    => '1000',
            'publicPrice'  => '2000',
            'stock'        => '10',
        ], $overrides), $this->createMock(Domains::class));
    }

    public function testNewProductHasNotSentLowStockAlert(): void
    {
        $product = new Products();
        $product->add($this->buildArgument());

        $this->assertFalse($product->hasLowStockAlertSent());
    }

    public function testMarkLowStockAlertSentSetsFlag(): void
    {
        $product = new Products();
        $product->add($this->buildArgument());

        $product->markLowStockAlertSent();

        $this->assertTrue($product->hasLowStockAlertSent());
    }

    public function testEditResetsLowStockAlertFlag(): void
    {
        $product = new Products();
        $product->add($this->buildArgument());
        $product->markLowStockAlertSent();
        $this->assertTrue($product->hasLowStockAlertSent());

        $product->edit($this->buildArgument(['stock' => '3']));

        $this->assertFalse($product->hasLowStockAlertSent());
    }

    public function testEditWithoutRestockingStillResetsFlag(): void
    {

        $product = new Products();
        $product->add($this->buildArgument(['stock' => '1']));
        $product->markLowStockAlertSent();

        $product->edit($this->buildArgument(['stock' => '1']));

        $this->assertFalse($product->hasLowStockAlertSent());
    }
}
