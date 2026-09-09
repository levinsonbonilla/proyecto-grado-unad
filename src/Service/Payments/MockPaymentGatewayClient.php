<?php

namespace App\Service\Payments;

use App\Interface\Service\Payments\PaymentGatewayClientInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MockPaymentGatewayClient implements PaymentGatewayClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $paymentGatewayInternalUrl,
    ) {
    }

    public function createIntent(string $orderId, int $amountCents, string $currency, string $returnUrl, string $webhookUrl): PaymentIntentResult
    {
        $response = $this->httpClient->request('POST', rtrim($this->paymentGatewayInternalUrl, '/') . '/v1/payment-intents', [
            'json' => [
                'orderId'    => $orderId,
                'amount'     => $amountCents,
                'currency'   => $currency,
                'returnUrl'  => $returnUrl,
                'webhookUrl' => $webhookUrl,
            ],
        ]);

        $data = $response->toArray();

        return new PaymentIntentResult(
            id: $data['id'],
            redirectUrl: $data['redirectUrl'],
            status: $data['status'],
        );
    }
}
