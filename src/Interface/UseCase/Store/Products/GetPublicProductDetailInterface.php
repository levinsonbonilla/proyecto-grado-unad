<?php

namespace App\Interface\UseCase\Store\Products;

use App\Entity\Products\Products;

interface GetPublicProductDetailInterface
{
    public function handler(Products $product): array;
}
