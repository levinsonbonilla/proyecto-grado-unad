<?php

namespace App\Service\Payments;

final readonly class PaymentIntentResult
{
    public function __construct(
        public string $id,
        public string $redirectUrl,
        public string $status,
    ) {
    }
}
