<?php

namespace App\Service\Products;

interface VariantLabelFormatterInterface
{

    public function format(?string $colorName, ?string $medidaName): string;
}
