<?php 

namespace App\Interface\UseCase\Dashboard\Domains;

use App\Entity\Tenants\Domains\Domains;
use App\ReturnHandler\FormReturn;

interface EditDomainInterface
{
    public function handler(Domains $domain): FormReturn;
}