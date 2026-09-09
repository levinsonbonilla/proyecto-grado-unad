<?php

namespace App\Interface\UseCase\Dashboard\Slides;

use App\ReturnHandler\FormReturn;

interface AddSlideInterface
{
    public function handler(string $type, ?array $additionalData = null): FormReturn;
}
