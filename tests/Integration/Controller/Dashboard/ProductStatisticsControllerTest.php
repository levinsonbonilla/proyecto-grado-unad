<?php

namespace App\Tests\Integration\Controller\Dashboard;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProductStatisticsControllerTest extends WebTestCase
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

        $client->request('GET', sprintf('/%s/dashboard/statistics/products', self::LOCALE));

        $this->assertResponseStatusCodeSame(302);
        $this->assertResponseRedirects();
    }

    /**
     * @dataProvider dataEndpointsProvider
     */
    public function testDataEndpointRequiresAuthentication(string $path): void
    {
        $client = static::createClient();

        $client->request('GET', sprintf('/%s/dashboard/statistics/products%s', self::LOCALE, $path));

        $this->assertResponseStatusCodeSame(302);
    }

    public static function dataEndpointsProvider(): array
    {
        return [
            'kpi'               => ['/data/kpi'],
            'funnel'            => ['/data/funnel'],
            'top-products'      => ['/data/top-products'],
            'sales-by-day'      => ['/data/sales-by-day'],
            'by-country'        => ['/data/by-country'],
            'by-region'         => ['/data/by-region'],
            'by-city'           => ['/data/by-city'],
            'avg-shipping-time' => ['/data/avg-shipping-time'],
        ];
    }

    public function testIndexRouteOnlyAcceptsGet(): void
    {
        $client = static::createClient();

        $client->request('POST', sprintf('/%s/dashboard/statistics/products', self::LOCALE));

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [302, 405]);
    }
}
