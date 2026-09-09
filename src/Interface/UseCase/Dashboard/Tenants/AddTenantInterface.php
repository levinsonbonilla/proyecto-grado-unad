<?php

namespace App\Interface\UseCase\Dashboard\Tenants;

use App\ReturnHandler\FormReturn;

interface AddTenantInterface
{
    public function handler(): FormReturn;
}