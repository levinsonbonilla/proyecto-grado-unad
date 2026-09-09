<?php

namespace App\Interface\UseCase\Dashboard\Users;

use App\ReturnHandler\FormReturn;

interface ProfileInterface
{
    public function handler(): FormReturn;    
}
