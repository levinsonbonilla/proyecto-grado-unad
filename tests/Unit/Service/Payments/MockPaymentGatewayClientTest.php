<?php

namespace App\Tests\Unit\Service\Payments;

use App\Service\Payments\MockPaymentGatewayClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class MockPaymentGatewayClientTest extends TestCase
{
    public function testCreateIntentSendsExpectedPayloadAndParsesResponse(): void
    {
        $capturedUrl = null;
        $capturedOptions = null;

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedOptions) {
            $capturedUrl = $url;
            $capturedOptions = $options;

            return new MockResponse(json_encode([
                'id'          => 'pi_abc123',
                'status'      => 'requires_action',
                'redirectUrl' => 'http://localhost:8070/pay/pi_abc123',
            ]));
        });

        $client = new MockPaymentGatewayClient($httpClient, 'http://payment-gateway:8070');

        $result = $client->createIntent(
            'order-1',
            50000,
            'COP',
            'https://proyecto-grado-unad.test/es/checkout/return/order-1',
            'https://proyecto-grado-unad.test/checkout/webhook/payment',
        );

        $this->assertSame('pi_abc123', $result->id);
        $this->assertSame('requires_action', $result->status);
        $this->assertSame('http://localhost:8070/pay/pi_abc123', $result->redirectUrl);

        $this->assertSame('http://payment-gateway:8070/v1/payment-intents', $capturedUrl);

        $body = json_decode($capturedOptions['body'], true);
        $this->assertSame('order-1', $body['orderId']);
        $this->assertSame(50000, $body['amount']);
        $this->assertSame('COP', $body['currency']);
        $this->assertSame('https://proyecto-grado-unad.test/es/checkout/return/order-1', $body['returnUrl']);
        $this->assertSame('https://proyecto-grado-unad.test/checkout/webhook/payment', $body['webhookUrl']);
    }

    public function testCreateIntentTrimsTrailingSlashFromInternalUrl(): void
    {
        $capturedUrl = null;

        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$capturedUrl) {
            $capturedUrl = $url;
            return new MockResponse(json_encode(['id' => 'pi_1', 'status' => 'requires_action', 'redirectUrl' => 'x']));
        });

        $client = new MockPaymentGatewayClient($httpClient, 'http://payment-gateway:8070/');
        $client->createIntent('order-1', 1000, 'COP', 'https://x/return', 'https://x/webhook');

        $this->assertSame('http://payment-gateway:8070/v1/payment-intents', $capturedUrl);
    }
}
