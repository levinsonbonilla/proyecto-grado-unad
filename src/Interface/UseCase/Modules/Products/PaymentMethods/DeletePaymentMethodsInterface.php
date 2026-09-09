<?php

namespace App\Interface\UseCase\Modules\Products\PaymentMethods;

use App\Entity\Tenants\Domains\PaymentMethods;

interface DeletePaymentMethodsInterface
{
    public function handler(PaymentMethods $entity): array;
}
