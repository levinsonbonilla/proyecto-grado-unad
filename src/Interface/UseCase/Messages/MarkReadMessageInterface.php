<?php

namespace App\Interface\UseCase\Messages;

use App\Entity\Users\HelpMessages;

interface MarkReadMessageInterface
{
    public function handler(HelpMessages $message): array;
}
