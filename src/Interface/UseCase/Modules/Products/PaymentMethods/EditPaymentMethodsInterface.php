<?php

namespace App\Interface\UseCase\Modules\Products\PaymentMethods;

use App\Entity\Tenants\Domains\PaymentMethods;
use App\ReturnHandler\FormReturn;

interface EditPaymentMethodsInterface
{
    public function handler(PaymentMethods $entity): FormReturn;
}
