<?php

namespace App\Interface\UseCase\Dashboard\AboutSections;

use App\Entity\Tenants\Others\AboutSections;
use App\ReturnHandler\FormReturn;

interface EditAboutSectionInterface
{
    public function handler(AboutSections $section): FormReturn;
}
