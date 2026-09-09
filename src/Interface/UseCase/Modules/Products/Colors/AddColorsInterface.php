<?php

namespace App\Interface\UseCase\Modules\Products\Colors;

use App\ReturnHandler\FormReturn;

interface AddColorsInterface
{
    public function handler(): FormReturn;
}
