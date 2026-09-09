<?php

namespace App\Interface\UseCase\Messages;

use App\ReturnHandler\FormReturn;

interface ComposeMessageInterface
{
    public function handler(string $type): FormReturn;
}
