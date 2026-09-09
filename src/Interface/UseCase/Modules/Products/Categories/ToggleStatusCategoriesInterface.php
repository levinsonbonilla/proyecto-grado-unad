<?php

namespace App\Interface\UseCase\Modules\Products\Categories;

use App\Entity\Products\Categories\Categories;

interface ToggleStatusCategoriesInterface
{
    public function handler(Categories $category): array;
}
