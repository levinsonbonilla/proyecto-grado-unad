<?php

namespace App\Interface\UseCase\Modules\Products\Manager;

use App\ReturnHandler\FormReturn;

interface AddManagerInterface
{
    public function handler(): FormReturn;
}
