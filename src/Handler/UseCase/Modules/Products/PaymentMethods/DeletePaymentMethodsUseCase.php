<?php

namespace App\Handler\UseCase\Modules\Products\PaymentMethods;

use App\Entity\Tenants\Domains\PaymentMethods;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Modules\Products\PaymentMethods\DeletePaymentMethodsInterface;
use App\Interface\UseCase\Security\LogInterface;

final class DeletePaymentMethodsUseCase implements DeletePaymentMethodsInterface
{
    public function __construct(
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly LogInterface $log,
    ) {
    }

    public function handler(PaymentMethods $entity): array
    {
        try {
            $entity->isActive() ? $entity->deactivate() : $entity->activate();
            $this->customeEntityManager->add($entity, true);
            return ['success' => true];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false];
        }
    }
}
