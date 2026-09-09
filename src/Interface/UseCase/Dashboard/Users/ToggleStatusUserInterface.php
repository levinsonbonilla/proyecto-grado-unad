<?php

namespace App\Interface\UseCase\Dashboard\Users;

use App\Entity\Users\Users;

interface ToggleStatusUserInterface
{
    public function handler(Users $user): array;
}
