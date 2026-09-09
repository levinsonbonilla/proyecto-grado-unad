<?php

namespace App\Interface\UseCase\Dashboard\Users;

use App\Entity\Users\Users;
use App\ReturnHandler\FormReturn;

interface EditUserInterface
{
    public function handler(Users $user): FormReturn;
}
