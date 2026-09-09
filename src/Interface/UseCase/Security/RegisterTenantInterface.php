<?php

namespace App\Interface\UseCase\Security;

use App\ReturnHandler\FormReturn;

interface RegisterTenantInterface
{
    public function handler(?array $data = []): FormReturn;
}
