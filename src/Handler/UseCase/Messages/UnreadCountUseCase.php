<?php

namespace App\Handler\UseCase\Messages;

use App\Entity\Users\Users;
use App\Interface\UseCase\Messages\UnreadCountInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\HelpMessagesRepository;
use Symfony\Bundle\SecurityBundle\Security;

final class UnreadCountUseCase implements UnreadCountInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly HelpMessagesRepository $repository,
        private readonly LogInterface $log,
    ) {
    }

    public function handler(): array
    {
        try {

            $user = $this->security->getUser();
            return ['count' => $this->repository->countUnread($user)];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['count' => 0];
        }
    }
}
