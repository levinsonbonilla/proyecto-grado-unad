<?php

namespace App\Interface\UseCase\Store\Products;

interface GetPublicProductsInterface
{
    public function handler(?string $categoryId = null, ?string $search = null, ?int $minPrice = null, ?int $maxPrice = null, int $page = 1, int $limit = 12): array;
}
