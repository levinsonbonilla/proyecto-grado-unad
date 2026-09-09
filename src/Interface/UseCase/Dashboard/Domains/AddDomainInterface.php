<?php

namespace App\Interface\UseCase\Dashboard\Domains;

use App\Entity\Tenants\Tenants;
use App\ReturnHandler\FormReturn;

interface AddDomainInterface
{
    
    public function handler(?Tenants $targetTenant = null): FormReturn;
}