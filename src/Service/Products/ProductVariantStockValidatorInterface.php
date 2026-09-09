<?php

namespace App\Service\Products;

interface ProductVariantStockValidatorInterface
{

    public function validate(?int $generalStock, array $variants): void;
}
