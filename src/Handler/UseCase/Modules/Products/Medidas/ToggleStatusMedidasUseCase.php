<?php

namespace App\Handler\UseCase\Modules\Products\Medidas;

use App\Entity\Products\Medidas\Medidas;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Modules\Products\Medidas\ToggleStatusMedidasInterface;
use App\Interface\UseCase\Security\LogInterface;

final class ToggleStatusMedidasUseCase implements ToggleStatusMedidasInterface
{
    public function __construct(
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly LogInterface $log
    ) {
    }

    public function handler(Medidas $medida): array
    {
        try {
            $medida->isActive() ? $medida->deactivate() : $medida->activate();
            $this->customeEntityManager->add($medida, true);
            return ['success' => true];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false];
        }
    }
}
