<?php 

namespace App\Interface\UseCase\Dashboard\Tenants;

use App\Entity\Tenants\Tenants;
use App\ReturnHandler\FormReturn;

interface EditTenantInterface
{
    public function handler(Tenants $tenant): FormReturn;
}