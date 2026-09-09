<?php

namespace App\Interface\UseCase\Modules\Products\Manager;

use App\Entity\Products\Products;

interface ToggleStatusManagerInterface
{
    public function handler(Products $product): array;
}
