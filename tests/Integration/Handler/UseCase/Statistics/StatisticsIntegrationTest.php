<?php

namespace App\Tests\Integration\Handler\UseCase\Statistics;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Statistics\Statistics;
use App\Entity\Tenants\Statistics\StatisticsEvents;
use App\Entity\Users\Users;
use App\Repository\Tenants\Statistics\StatisticsEventsRepository;
use App\Repository\Tenants\Statistics\StatisticsRepository;
use App\Repository\Users\UsersRepository;
use App\Tests\Integration\IntegrationTestCase;

class StatisticsIntegrationTest extends IntegrationTestCase
{
    private StatisticsRepository       $repo;
    private StatisticsEventsRepository $eventsRepo;
    private Domains                    $domain;
    private \DateTimeImmutable         $from;
    private \DateTimeImmutable         $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo       = $this->em->getRepository(Statistics::class);
        $this->eventsRepo = $this->em->getRepository(StatisticsEvents::class);
        $this->domain     = $this->em->getRepository(Domains::class)->findAll()[0];
        $this->from       = new \DateTimeImmutable('-1 day');
        $this->to         = new \DateTimeImmutable('+1 day');
    }

    public function testFixtureCreatesExpectedStatisticsRecords(): void
    {
        $records = $this->em->getRepository(Statistics::class)->findAll();
         $this->assertCount(295, $records);
    }

    public function testFixtureRecordsAreActive(): void
    {
        $records = $this->em->getRepository(Statistics::class)->findAll();
        foreach ($records as $record) {
            $this->assertTrue($record->isActive());
        }
    }

    public function testGetTotalVisitsReturnsThree(): void
    {
        $total = $this->repo->getTotalVisits($this->domain, $this->from, $this->to);
        $this->assertSame(3, $total);
    }

    public function testGetTotalVisitsReturnsZeroOutsideDateRange(): void
    {

        $from = new \DateTimeImmutable('-200 days');
        $to   = new \DateTimeImmutable('-95 days');
        $this->assertSame(0, $this->repo->getTotalVisits($this->domain, $from, $to));
    }

    public function testGetUniqueVisitorsReturnsTwoDistinctIps(): void
    {

        $unique = $this->repo->getUniqueVisitors($this->domain, $this->from, $this->to);
        $this->assertSame(2, $unique);
    }

    public function testGetUniqueSessionsReturnsThree(): void
    {
        $sessions = $this->repo->getUniqueSessions($this->domain, $this->from, $this->to);
        $this->assertSame(3, $sessions);
    }

    public function testGetKpiSummaryReturnsAllFourKeys(): void
    {
        $kpi = $this->repo->getKpiSummary($this->domain, $this->from, $this->to);

        $this->assertArrayHasKey('totalVisits',    $kpi);
        $this->assertArrayHasKey('uniqueVisitors', $kpi);
        $this->assertArrayHasKey('uniqueSessions', $kpi);
        $this->assertArrayHasKey('countriesCount', $kpi);
    }

    public function testGetKpiSummaryValuesAreCorrect(): void
    {
        $kpi = $this->repo->getKpiSummary($this->domain, $this->from, $this->to);

        $this->assertSame(3, $kpi['totalVisits']);
        $this->assertSame(2, $kpi['uniqueVisitors']);
        $this->assertSame(3, $kpi['uniqueSessions']);
    }

    public function testGetVisitsByDayReturnsArrayWithDateAndVisits(): void
    {
        $result = $this->repo->getVisitsByDay($this->domain, $this->from, $this->to);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('date',   $result[0]);
        $this->assertArrayHasKey('visits', $result[0]);
    }

    public function testGetVisitsByDayTotalMatchesTotalVisits(): void
    {
        $result    = $this->repo->getVisitsByDay($this->domain, $this->from, $this->to);
        $sumVisits = array_sum(array_column($result, 'visits'));
        $this->assertSame(3, (int) $sumVisits);
    }

    public function testGetTopBrowsersReturnsChromAndFirefox(): void
    {
        $result   = $this->repo->getTopBrowsers($this->domain, $this->from, $this->to);
        $browsers = array_column($result, 'browser');

        $this->assertContains('Chrome',  $browsers);
        $this->assertContains('Firefox', $browsers);
    }

    public function testGetTopBrowsersChromHasTwoVisits(): void
    {
        $result = $this->repo->getTopBrowsers($this->domain, $this->from, $this->to);
        $chrome = array_filter($result, fn($r) => $r['browser'] === 'Chrome');
        $chrome = reset($chrome);

        $this->assertSame(2, (int) $chrome['visits']);
    }

    public function testGetTopDevicesReturnsDesktopAndMobile(): void
    {
        $result  = $this->repo->getTopDevices($this->domain, $this->from, $this->to);
        $devices = array_column($result, 'device');

        $this->assertContains('desktop', $devices);
        $this->assertContains('mobile',  $devices);
    }

    public function testGetTopPagesReturnsShopAndHome(): void
    {
        $result = $this->repo->getTopPages($this->domain, $this->from, $this->to);
        $pages  = array_column($result, 'page');

        $this->assertContains('/es/shop', $pages);
        $this->assertContains('/es/home', $pages);
    }

    public function testGetTopPagesShopHasTwoVisits(): void
    {
        $result = $this->repo->getTopPages($this->domain, $this->from, $this->to);
        $shop   = array_filter($result, fn($r) => $r['page'] === '/es/shop');
        $shop   = reset($shop);

        $this->assertSame(2, (int) $shop['visits']);
    }

    public function testGetTopReferrersIncludesGoogleAndDirect(): void
    {
        $result   = $this->repo->getTopReferrers($this->domain, $this->from, $this->to);
        $referrers = array_column($result, 'referrer');

        $this->assertContains('google.com', $referrers);
        $this->assertContains('Direct',     $referrers);
    }

    public function testGetTopCampaignsReturnsBlackFriday(): void
    {
        $result    = $this->repo->getTopCampaigns($this->domain, $this->from, $this->to);
        $campaigns = array_column($result, 'campaign');

        $this->assertContains('black_friday', $campaigns);
    }

    public function testGetTopOperativeSystemsReturnsWindows(): void
    {
        $result = $this->repo->getTopOperativeSystems($this->domain, $this->from, $this->to);
        $oses   = array_column($result, 'os');

        $this->assertContains('Windows', $oses);
    }

    public function testGetVisitorsByAuthStatusSeparatesGuestsFromRegistered(): void
    {
        $user = $this->em->getRepository(Users::class)->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $this->assertNotNull($user);

        $loggedInVisit = (new Statistics())->add(
            domain: $this->domain,
            ip: '203.0.113.5',
            device: 'desktop',
            country: null, region: null, city: null,
            browser: 'Chrome', operativeSystem: 'Windows', lang: 'es',
            page: '/es/profile', referrer: null, referrerDomain: null,
            utmSource: null, utmMedium: null, utmCampaign: null,
            sessionId: 'session-logged-in', countryIsoCode: null,
            user: $user,
        );
        $this->em->persist($loggedInVisit);
        $this->em->flush();

        $result = $this->repo->getVisitorsByAuthStatus($this->domain, $this->from, $this->to);
        $byStatus = array_column($result, 'visits', 'status');

        $this->assertSame(1, (int) ($byStatus['registered'] ?? 0));
        $this->assertSame(3, (int) ($byStatus['guest'] ?? 0));
    }

    private function addEvent(string $eventName, ?Users $user = null, ?string $sessionId = null): StatisticsEvents
    {
        $event = (new StatisticsEvents())->add(
            domain: $this->domain,
            eventName: $eventName,
            eventTarget: 'target-1',
            page: '/es/shop',
            metadata: null,
            sessionId: $sessionId ?? 'session-events-1',
            user: $user,
        );
        $this->em->persist($event);

        return $event;
    }

    public function testGetTopEventsReturnsEventsOrderedByCount(): void
    {
        $this->addEvent('product_click');
        $this->addEvent('product_click');
        $this->addEvent('add_to_cart');
        $this->em->flush();

        $result = $this->eventsRepo->getTopEvents($this->domain, $this->from, $this->to);
        $byEvent = array_column($result, 'total', 'event');

        $this->assertSame(2, (int) $byEvent['product_click']);
        $this->assertSame(1, (int) $byEvent['add_to_cart']);
        $this->assertSame('product_click', $result[0]['event']);
    }

    public function testGetTopEventsReturnsEmptyOutsideDateRange(): void
    {
        $this->addEvent('product_click');
        $this->em->flush();

        $from = new \DateTimeImmutable('-200 days');
        $to   = new \DateTimeImmutable('-95 days');
        $this->assertSame([], $this->eventsRepo->getTopEvents($this->domain, $from, $to));
    }

    public function testGetEventsByDayFiltersByEventName(): void
    {
        $this->addEvent('product_click');
        $this->addEvent('add_to_cart');
        $this->em->flush();

        $result = $this->eventsRepo->getEventsByDay($this->domain, 'product_click', $this->from, $this->to);

        $this->assertNotEmpty($result);
        $this->assertSame(1, (int) array_sum(array_column($result, 'total')));
    }

    public function testGetDistinctIdentifiersForEventPrefersUserOverSession(): void
    {
        $user = $this->em->getRepository(Users::class)->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $this->assertNotNull($user);

        $this->addEvent('add_to_cart', user: $user, sessionId: 'session-a');
        $this->addEvent('add_to_cart', user: null, sessionId: 'session-b');
        $this->em->flush();

        $identifiers = $this->eventsRepo->getDistinctIdentifiersForEvent($this->domain, 'add_to_cart', $this->from, $this->to);

        $this->assertContains((string) $user->getId(), $identifiers);
        $this->assertContains('session-b', $identifiers);
        $this->assertNotContains('session-a', $identifiers);
    }

    public function testGetDistinctIdentifiersReturnsThreeBaseSessions(): void
    {

        $identifiers = $this->repo->getDistinctIdentifiers($this->domain, $this->from, $this->to);
        $this->assertCount(3, $identifiers);
    }

    public function testGetDistinctIdentifiersFiltersByPagePattern(): void
    {

        $identifiers = $this->repo->getDistinctIdentifiers($this->domain, $this->from, $this->to, '%/product/%');
        $this->assertSame([], $identifiers);
    }

    public function testGetDistinctIdentifiersUsesUserIdWhenLoggedIn(): void
    {
        $user = $this->em->getRepository(Users::class)->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $this->assertNotNull($user);

        $visit = (new Statistics())->add(
            domain: $this->domain,
            ip: '203.0.113.9',
            device: 'desktop',
            country: null, region: null, city: null,
            browser: 'Chrome', operativeSystem: 'Windows', lang: 'es',
            page: '/es/profile', referrer: null, referrerDomain: null,
            utmSource: null, utmMedium: null, utmCampaign: null,
            sessionId: 'session-identifiers-user', countryIsoCode: null,
            user: $user,
        );
        $this->em->persist($visit);
        $this->em->flush();

        $identifiers = $this->repo->getDistinctIdentifiers($this->domain, $this->from, $this->to);

        $this->assertContains((string) $user->getId(), $identifiers);
        $this->assertNotContains('session-identifiers-user', $identifiers);
    }
}
