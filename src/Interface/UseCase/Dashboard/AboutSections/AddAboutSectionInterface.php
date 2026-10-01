<?php

namespace App\Interface\UseCase\Dashboard\AboutSections;

use App\ReturnHandler\FormReturn;

interface AddAboutSectionInterface
{
    public function handler(): FormReturn;
}
