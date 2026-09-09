<?php

namespace App\Interface\UseCase\Security;

use App\ReturnHandler\PasswordRecoveryReturn;

interface PasswordRecoveryInterface
{
    public function handler(string $userUuid): PasswordRecoveryReturn;
}