<?php

namespace App\Interface\UseCase\Messages;

use App\Entity\Users\Users;

interface MessageNotificationInterface
{
    public function notify(Users $recipient, Users $sender, string $messageText): void;
}
