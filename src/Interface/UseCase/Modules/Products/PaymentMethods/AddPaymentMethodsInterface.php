<?php

namespace App\Interface\UseCase\Modules\Products\PaymentMethods;

use App\ReturnHandler\FormReturn;

interface AddPaymentMethodsInterface
{
    public function handler(string $type, ?array $additionalData = null): FormReturn;
}
