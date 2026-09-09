<?php

namespace App\Interface\UseCase\Modules\Products\Colors;

use App\Entity\Products\Colors\Colors;

interface ToggleStatusColorsInterface
{
    public function handler(Colors $color): array;
}
