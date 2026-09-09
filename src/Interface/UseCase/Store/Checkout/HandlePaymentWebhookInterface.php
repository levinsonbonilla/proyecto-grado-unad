<?php

namespace App\Interface\UseCase\Store\Checkout;

interface HandlePaymentWebhookInterface
{

    public function handler(string $rawBody, string $signature): array;
}
