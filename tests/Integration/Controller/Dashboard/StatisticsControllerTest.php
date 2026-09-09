<?php

namespace App\Tests\Integration\Controller\Dashboard;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StatisticsControllerTest extends WebTestCase
{
    private const LOCALE = 'es';

    protected function setUp(): void
    {
        parent::setUp();
        self::ensureKernelShutdown();
    }

    public function testIndexRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', sprintf('/%s/dashboard/statistics', self::LOCALE));

        $this->assertResponseStatusCodeSame(302);
        $this->assertResponseRedirects();
    }

    public function testKpiEndpointRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', sprintf('/%s/dashboard/statistics/data/kpi', self::LOCALE));

        $this->assertResponseStatusCodeSame(302);
    }

    public function testByAuthStatusEndpointRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', sprintf('/%s/dashboard/statistics/data/by-auth-status', self::LOCALE));

        $this->assertResponseStatusCodeSame(302);
    }

    public function testByEventEndpointRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', sprintf('/%s/dashboard/statistics/data/by-event', self::LOCALE));

        $this->assertResponseStatusCodeSame(302);
    }

    public function testEventByDayEndpointRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', sprintf('/%s/dashboard/statistics/data/event/product_click/by-day', self::LOCALE));

        $this->assertResponseStatusCodeSame(302);
    }

    public function testFunnelEndpointNoLongerExistsHere(): void
    {
        $client = static::createClient();

        $client->request('GET', sprintf('/%s/dashboard/statistics/data/funnel', self::LOCALE));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testIndexRouteOnlyAcceptsGet(): void
    {
        $client = static::createClient();

        $client->request('POST', sprintf('/%s/dashboard/statistics', self::LOCALE));

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [302, 405]);
    }
}
