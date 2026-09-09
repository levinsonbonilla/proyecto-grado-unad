<?php

namespace App\Interface\UseCase\Modules\Products\Categories;

use App\ReturnHandler\FormReturn;

interface AddCategoriesInterface
{
    public function handler(): FormReturn;
}
