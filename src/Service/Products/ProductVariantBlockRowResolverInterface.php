<?php

namespace App\Service\Products;

use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Products;
use App\Entity\Tenants\Domains\Domains;

interface ProductVariantBlockRowResolverInterface
{

    public function resolve(array $block, ?string $coverImageUrl, Products $product, Domains $domain, array $existingBlocksById = []): array;
}
