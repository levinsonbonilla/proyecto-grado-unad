<?php

namespace App\Tests\Integration\Controller\Store;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PaymentWebhookControllerTest extends WebTestCase
{

    private const HOST = ['HTTP_HOST' => 'localhost:8060'];

    protected function setUp(): void
    {
        parent::setUp();
        self::ensureKernelShutdown();
    }

    public function testWebhookIsPublicUnlikeCheckoutRoutes(): void
    {
        $client = static::createClient();

        $client->request('POST', '/checkout/webhook/payment', server: self::HOST + ['CONTENT_TYPE' => 'application/json'], content: '{}');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testWebhookRejectsInvalidSignature(): void
    {
        $client = static::createClient();

        $body = json_encode(['intentId' => 'pi_test', 'status' => 'succeeded']);
        $client->request(
            'POST',
            '/checkout/webhook/payment',
            server: self::HOST + ['CONTENT_TYPE' => 'application/json', 'HTTP_X_GATEWAY_SIGNATURE' => 'firma-incorrecta'],
            content: $body,
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testWebhookOnlyAcceptsPost(): void
    {
        $client = static::createClient();

        $client->request('GET', '/checkout/webhook/payment', server: self::HOST);

        $this->assertResponseStatusCodeSame(405);
    }
}
