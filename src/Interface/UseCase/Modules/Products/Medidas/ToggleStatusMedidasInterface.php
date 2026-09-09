<?php

namespace App\Interface\UseCase\Modules\Products\Medidas;

use App\Entity\Products\Medidas\Medidas;

interface ToggleStatusMedidasInterface
{
    public function handler(Medidas $medida): array;
}
