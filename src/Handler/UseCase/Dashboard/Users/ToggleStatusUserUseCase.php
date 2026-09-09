<?php

namespace App\Handler\UseCase\Dashboard\Users;

use App\Entity\Users\Users;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Dashboard\Users\ToggleStatusUserInterface;
use App\Interface\UseCase\Security\LogInterface;

final class ToggleStatusUserUseCase implements ToggleStatusUserInterface
{
    public function __construct(
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly LogInterface $log
    ) {
    }

    public function handler(Users $user): array
    {
        try {
            $user->isActive() ? $user->deactivate() : $user->activate();
            $this->customeEntityManager->add($user, true);
            return ['success' => true];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false];
        }
    }
}
