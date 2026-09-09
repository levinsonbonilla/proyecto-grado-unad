<?php

namespace App\Interface\UseCase\Modules\Products\Categories;

use App\Entity\Products\Categories\Categories;
use App\ReturnHandler\FormReturn;

interface EditCategoriesInterface
{
    public function handler(Categories $categorie): FormReturn;
}
