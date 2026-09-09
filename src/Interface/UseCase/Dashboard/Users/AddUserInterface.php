<?php

namespace App\Interface\UseCase\Dashboard\Users;

use App\ReturnHandler\FormReturn;

interface AddUserInterface
{
    public function handler(): FormReturn;
}
