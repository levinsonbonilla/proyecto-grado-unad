<?php

namespace App\Interface\UseCase\Store\Checkout;

interface ConfirmOrderInterface
{
    public function handler(array $data): array;
}
