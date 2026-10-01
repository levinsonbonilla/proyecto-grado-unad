<?php

namespace App\Interface\UseCase\Dashboard\AboutSections;

use App\Entity\Tenants\Others\AboutSections;

interface ToggleStatusAboutSectionInterface
{
    public function handler(AboutSections $section): array;
}
