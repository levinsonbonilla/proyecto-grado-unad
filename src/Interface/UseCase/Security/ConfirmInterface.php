<?php

namespace App\Interface\UseCase\Security;

use App\Entity\Users\Users;

interface ConfirmInterface
{
    public function handler(string $userUuid): Users;
}
