<?php

namespace App\Interface\UseCase\Modules\Products\Medidas;

use App\Entity\Products\Medidas\Medidas;
use App\ReturnHandler\FormReturn;

interface EditMedidasInterface
{
    public function handler(Medidas $medida): FormReturn;
}
