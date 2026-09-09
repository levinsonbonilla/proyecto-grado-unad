<?php

namespace App\Interface\UseCase\Messages;

interface InboxMessagesInterface
{
    public function handler(): array;
}
