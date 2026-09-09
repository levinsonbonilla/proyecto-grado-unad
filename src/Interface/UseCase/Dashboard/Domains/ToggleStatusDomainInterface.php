<?php

namespace App\Interface\UseCase\Dashboard\Domains;

use App\Entity\Tenants\Domains\Domains;

interface ToggleStatusDomainInterface
{
    public function handler(Domains $domain): array;
}
