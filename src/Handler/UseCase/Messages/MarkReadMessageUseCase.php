<?php

namespace App\Handler\UseCase\Messages;

use App\Entity\Users\HelpMessages;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Messages\MarkReadMessageInterface;
use App\Interface\UseCase\Security\LogInterface;

final class MarkReadMessageUseCase implements MarkReadMessageInterface
{
    public function __construct(
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly LogInterface $log,
    ) {
    }

    public function handler(HelpMessages $message): array
    {
        try {
            $message->isRead() ? $message->markAsUnread() : $message->markAsRead();
            $this->customeEntityManager->add($message, true);
            return ['success' => true, 'isRead' => $message->isRead()];
        } catch (\Throwable $th) {
            $this->log->handler(throwable: $th);
            return ['success' => false];
        }
    }
}
