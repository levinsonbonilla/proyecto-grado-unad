<?php

namespace App\Interface\UseCase\Messages;

use App\Entity\Users\HelpMessages;

interface ViewThreadInterface
{
    public function handler(HelpMessages $message): array;
}
