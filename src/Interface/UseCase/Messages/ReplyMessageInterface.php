<?php

namespace App\Interface\UseCase\Messages;

use App\Entity\Users\HelpMessages;
use App\ReturnHandler\FormReturn;

interface ReplyMessageInterface
{
    public function handler(HelpMessages $parent, string $type): FormReturn;
}
