<?php

namespace App\Interface\UseCase\Dashboard\Slides;

use App\Entity\Tenants\Others\Slides;

interface DeleteSlideInterface
{
    public function handler(Slides $slide): array;
}
