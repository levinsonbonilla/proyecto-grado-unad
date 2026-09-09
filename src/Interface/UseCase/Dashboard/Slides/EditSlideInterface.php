<?php

namespace App\Interface\UseCase\Dashboard\Slides;

use App\Entity\Tenants\Others\Slides;
use App\ReturnHandler\FormReturn;

interface EditSlideInterface
{
    public function handler(Slides $slide): FormReturn;
}
