<?php

namespace App\Interface\UseCase\General;

use App\Entity\Products\Products;

interface GetProductInterface
{
    public function handler(Products $product): array;
}
