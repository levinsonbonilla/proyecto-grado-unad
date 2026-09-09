<?php

namespace App\Tests\Integration\Handler\UseCase\Statistics;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Statistics\Statistics;
use App\Entity\Tenants\Statistics\StatisticsEvents;
use App\Entity\Users\Users;
use App\Handler\UseCase\Statistics\GetFunnelCountsHandler;
use App\Tests\Integration\IntegrationTestCase;

class GetFunnelCountsHandlerIntegrationTest extends IntegrationTestCase
{
    private GetFunnelCountsHandler $handler;
    private Domains $domain;
    private \DateTimeImmutable $from;
    private \DateTimeImmutable $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = static::getContainer()->get(GetFunnelCountsHandler::class);
        $this->domain = $this->em->getRepository(Domains::class)
            ->findOneBy(['domain' => 'http://localhost:8060', 'active' => true]);
        $this->assertNotNull($this->domain);

        $this->from = new \DateTimeImmutable('-1 day');
        $this->to   = new \DateTimeImmutable('+1 day');

        $this->buildScenario();
    }

    private function addVisit(?Users $user, string $sessionId, string $page): void
    {
        $visit = (new Statistics())->add(
            domain: $this->domain,
            ip: '203.0.113.10',
            device: 'desktop',
            country: null, region: null, city: null,
            browser: 'Chrome', operativeSystem: 'Windows', lang: 'es',
            page: $page, referrer: null, referrerDomain: null,
            utmSource: null, utmMedium: null, utmCampaign: null,
            sessionId: $sessionId, countryIsoCode: null,
            user: $user,
        );
        $this->em->persist($visit);
    }

    private function addEvent(?Users $user, string $sessionId, string $eventName): void
    {
        $event = (new StatisticsEvents())->add(
            domain: $this->domain,
            eventName: $eventName,
            eventTarget: 'product-fixture',
            page: '/es/product/fixture',
            metadata: null,
            sessionId: $sessionId,
            user: $user,
        );
        $this->em->persist($event);
    }

    private function buildScenario(): void
    {
        $customer = $this->em->getRepository(Users::class)
            ->findOneBy(['email' => 'customer.test@proyecto-grado.test']);
        $this->assertNotNull($customer, 'El fixture de Orders depende de customer.test@proyecto-grado.test');

        $this->addVisit($customer, 'session-customer', '/es/shop');
        $this->addVisit($customer, 'session-customer', '/es/product/fixture');
        $this->addEvent($customer, 'session-customer', 'add_to_cart');

        $this->addVisit(null, 'session-b', '/es/shop');
        $this->addVisit(null, 'session-b', '/es/product/otro');

        $this->addVisit(null, 'session-c', '/es/shop');

        $this->em->flush();
    }

    public function testFunnelCountsMatchTheFullScenario(): void
    {
        $result = $this->handler->handler($this->domain, $this->from, $this->to);

        $this->assertSame(6, $result['visited']);
        $this->assertSame(2, $result['viewedProduct']);
        $this->assertSame(1, $result['addedToCart']);
        $this->assertSame(1, $result['purchased']);
    }
}
