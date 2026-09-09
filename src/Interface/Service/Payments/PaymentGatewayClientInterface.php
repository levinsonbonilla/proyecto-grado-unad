<?php

namespace App\Interface\Service\Payments;

use App\Service\Payments\PaymentIntentResult;

interface PaymentGatewayClientInterface
{

    public function createIntent(string $orderId, int $amountCents, string $currency, string $returnUrl, string $webhookUrl): PaymentIntentResult;
}
