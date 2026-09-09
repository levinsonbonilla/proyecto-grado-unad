<?php

namespace App\Interface\UseCase\Dashboard\Users;

use App\Entity\Users\Users;

interface ResendWelcomeEmailInterface
{
    public function handler(Users $user): array;
}
