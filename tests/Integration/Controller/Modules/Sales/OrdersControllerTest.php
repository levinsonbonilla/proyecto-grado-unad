<?php

namespace App\Tests\Integration\Controller\Modules\Sales;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OrdersControllerTest extends WebTestCase
{
    private const LOCALE = 'es';

    protected function setUp(): void
    {
        parent::setUp();
        self::ensureKernelShutdown();
    }

    public function testListRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', sprintf('/%s/dashboard/sales/orders', self::LOCALE));

        $this->assertResponseStatusCodeSame(302);
        $this->assertResponseRedirects();
    }

    public function testListApiRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', sprintf('/%s/dashboard/sales/orders/list', self::LOCALE));

        $this->assertResponseStatusCodeSame(302);
    }

    public function testDetailRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', sprintf('/%s/dashboard/sales/orders/00000000-0000-0000-0000-000000000000', self::LOCALE));

        $this->assertResponseStatusCodeSame(302);
    }

    public function testUpdateStatusRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('POST', sprintf('/%s/dashboard/sales/orders/00000000-0000-0000-0000-000000000000/status', self::LOCALE));

        $this->assertResponseStatusCodeSame(302);
    }

    public function testUpdateStatusRouteOnlyAcceptsPost(): void
    {
        $client = static::createClient();

        $client->request('GET', sprintf('/%s/dashboard/sales/orders/00000000-0000-0000-0000-000000000000/status', self::LOCALE));

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [302, 405]);
    }
}
