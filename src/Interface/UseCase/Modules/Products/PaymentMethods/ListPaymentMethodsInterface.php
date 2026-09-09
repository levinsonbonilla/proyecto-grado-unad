<?php

namespace App\Interface\UseCase\Modules\Products\PaymentMethods;

interface ListPaymentMethodsInterface
{
    public function handler(): array;
}
