<?php

namespace App\Handler\UseCase\Dashboard\Tenants;

use App\Entity\Tenants\Tenants;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Dashboard\Tenants\ToggleStatusTenantInterface;
use App\Interface\UseCase\Security\LogInterface;

final class ToggleStatusTenantUseCase implements ToggleStatusTenantInterface
{
    public function __construct(
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly LogInterface $log
    ) {
    }

    public function handler(Tenants $tenant): array
    {
        try {
            $tenant->isActive() ? $tenant->deactivate() : $tenant->activate();
            $this->customeEntityManager->add($tenant, true);
            return ['success' => true];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false];
        }
    }
}
