<?php

namespace App\Handler\UseCase\Modules\Products\Colors;

use App\Entity\Products\Colors\Colors;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Modules\Products\Colors\ToggleStatusColorsInterface;
use App\Interface\UseCase\Security\LogInterface;

final class ToggleStatusColorsUseCase implements ToggleStatusColorsInterface
{
    public function __construct(
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly LogInterface $log
    ) {
    }

    public function handler(Colors $color): array
    {
        try {
            $color->isActive() ? $color->deactivate() : $color->activate();
            $this->customeEntityManager->add($color, true);
            return ['success' => true];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false];
        }
    }
}
