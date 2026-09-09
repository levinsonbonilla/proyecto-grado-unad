<?php

namespace App\Interface\UseCase\Dashboard\Users;

use App\Entity\Tenants\Tenants;

interface GetDomainsInterface
{
    public function handler(Tenants $tenant): array;
}
