<?php

namespace App\Interface\UseCase\Modules\Products\Medidas;

use App\ReturnHandler\FormReturn;

interface AddMedidasInterface
{
    public function handler(): FormReturn;
}
