<?php

namespace App\Service\Products;

use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Products;

interface LowStockAlertInterface
{

    public function markProductIfLow(Products $product): bool;

    public function markBlockIfLow(ProductsColors $block): bool;

    public function notifyProduct(Products $product): void;

    public function notifyBlock(ProductsColors $block): void;
}
