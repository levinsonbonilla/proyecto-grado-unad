<?php

namespace App\Interface\UseCase\Messages;

interface SentMessagesInterface
{
    public function handler(): array;
}
