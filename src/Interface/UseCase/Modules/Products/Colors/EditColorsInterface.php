<?php

namespace App\Interface\UseCase\Modules\Products\Colors;

use App\Entity\Products\Colors\Colors;
use App\ReturnHandler\FormReturn;

interface EditColorsInterface
{
    public function handler(Colors $color): FormReturn;
}
