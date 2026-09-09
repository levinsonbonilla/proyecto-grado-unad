<?php

namespace App\Interface\UseCase\Modules\Products\Manager;

use App\Entity\Products\Categories\Categories;
use App\Entity\Products\Products;
use App\ReturnHandler\FormReturn;

interface EditManagerInterface
{
    public function handler(Products $entity): FormReturn;
}
