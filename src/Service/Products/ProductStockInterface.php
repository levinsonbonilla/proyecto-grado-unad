<?php

namespace App\Service\Products;

use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Products;

interface ProductStockInterface
{
    public function available(Products $product, ?ProductsColors $block = null): ?int;

    public function currentAvailable(Products $product, ?ProductsColors $block = null): ?int;

    public function reserve(Products $product, ?ProductsColors $block, int $quantity): bool;

    public function release(Products $product, ?ProductsColors $block, int $quantity): void;
}
