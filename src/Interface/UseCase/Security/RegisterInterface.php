<?php

namespace App\Interface\UseCase\Security;

use App\ReturnHandler\FormReturn;

interface RegisterInterface
{
    public function handler(?array $data = []): FormReturn ;
}