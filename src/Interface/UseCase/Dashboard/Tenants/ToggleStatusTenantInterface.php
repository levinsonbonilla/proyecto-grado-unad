<?php

namespace App\Interface\UseCase\Dashboard\Tenants;

use App\Entity\Tenants\Tenants;

interface ToggleStatusTenantInterface
{
    public function handler(Tenants $tenant): array;
}
