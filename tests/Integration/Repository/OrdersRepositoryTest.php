<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Repository\Products\Orders\OrdersRepository;
use App\Tests\Integration\IntegrationTestCase;

class OrdersRepositoryTest extends IntegrationTestCase
{
    private OrdersRepository $repository;
    private Domains $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = static::getContainer()->get(OrdersRepository::class);
        $this->domain = $this->em->getRepository(Domains::class)
            ->findOneBy(['domain' => 'http://localhost:8060', 'active' => true]);

        $this->assertNotNull($this->domain, 'El domain localhost:8060 debe existir en fixtures');
    }

    public function testGetListCountsAllFourFixtureOrders(): void
    {
        $result = $this->repository->getList($this->domain, isCount: true);
        $this->assertSame(4, (int) reset($result));
    }

    public function testGetListReturnsExpectedColumns(): void
    {
        $result = $this->repository->getList($this->domain, isCount: false);

        $this->assertCount(4, $result);
        $row = reset($result);
        foreach (['id', 'totalAmount', 'createdAt', 'trackingNumber', 'statusName', 'customerName', 'customerEmail'] as $key) {
            $this->assertArrayHasKey($key, $row);
        }
    }

    public function testGetListFiltersByStatus(): void
    {
        $result = $this->repository->getList($this->domain, isCount: false, statusFilter: 'Enviado');

        $this->assertCount(1, $result);
        $this->assertSame('Enviado', reset($result)['statusName']);
    }

    public function testGetAdminDetailReturnsShippingAndTracking(): void
    {
        $shipped = $this->repository->getList($this->domain, isCount: false, statusFilter: 'Enviado');
        $shippedId = (string) reset($shipped)['id'];

        $order = $this->em->getRepository(\App\Entity\Products\Orders\Orders::class)->find($shippedId);
        $this->assertNotNull($order);

        $detail = $this->repository->getAdminDetail($order);

        $this->assertNotNull($detail);
        $this->assertSame('TRK-0001', $detail['trackingNumber']);
        $this->assertSame('Servientrega', $detail['trackingCarrier']);
        $this->assertNotEmpty($detail['customerEmail']);
    }

    public function testGetAdminDetailResolvesCountryAndRegionNameForLocale(): void
    {
        $shipped = $this->repository->getList($this->domain, isCount: false, statusFilter: 'Enviado');
        $shippedId = (string) reset($shipped)['id'];
        $order = $this->em->getRepository(\App\Entity\Products\Orders\Orders::class)->find($shippedId);

        $detail = $this->repository->getAdminDetail($order, 'es');

        $this->assertSame('Colombia', $detail['countryName']);
        $this->assertSame('Bogotá D.C.', $detail['regionName']);
        $this->assertStringNotContainsString('{', $detail['countryName']);
        $this->assertStringNotContainsString('{', $detail['regionName']);
    }

    public function testFindByUserReturnsAllFixtureOrdersForOwner(): void
    {
        $customer = $this->em->getRepository(Users::class)
            ->findOneBy(['email' => 'customer.test@proyecto-grado.test']);
        $this->assertNotNull($customer, 'El usuario de fixture customer.test@proyecto-grado.test debe existir');

        $result = $this->repository->findByUser($customer, 'es');

        $this->assertCount(4, $result);

        $row = reset($result);
        $this->assertSame('Colombia', $row['countryName']);
        $this->assertSame('Bogotá D.C.', $row['regionName']);

        $resultEn = $this->repository->findByUser($customer, 'en');
        $this->assertSame('Bogota D.C.', reset($resultEn)['regionName']);
    }

    public function testFindDetailByIdReturnsRowForOwner(): void
    {
        $customer = $this->em->getRepository(Users::class)
            ->findOneBy(['email' => 'customer.test@proyecto-grado.test']);
        $this->assertNotNull($customer);

        $shipped = $this->repository->getList($this->domain, isCount: false, statusFilter: 'Enviado');
        $shippedId = (string) reset($shipped)['id'];

        $detail = $this->repository->findDetailById($shippedId, $customer, 'es');

        $this->assertNotNull($detail);
        $this->assertSame('TRK-0001', $detail['trackingNumber']);
        $this->assertSame('Servientrega', $detail['trackingCarrier']);
        $this->assertSame('Colombia', $detail['countryName']);
        $this->assertSame('Bogotá D.C.', $detail['regionName']);
    }

    public function testGetSalesKpiSummaryCountsPendingAndShippableOrders(): void
    {
        $from = new \DateTimeImmutable('-1 day');
        $to   = new \DateTimeImmutable('+1 day');

        $kpi = $this->repository->getSalesKpiSummary($this->domain, $from, $to);

        $this->assertArrayHasKey('totalVentas', $kpi);
        $this->assertArrayHasKey('pedidosPendientesPago', $kpi);
        $this->assertArrayHasKey('pedidosPorEnviar', $kpi);
        $this->assertArrayHasKey('ticketPromedio', $kpi);

        $this->assertSame(1, $kpi['pedidosPendientesPago']);

        $this->assertSame(1, $kpi['pedidosPorEnviar']);

        $this->assertGreaterThan(0, $kpi['totalVentas']);
    }

    public function testGetDistinctBuyerIdsReturnsOneCustomerForFixtureOrders(): void
    {
        $from = new \DateTimeImmutable('-1 day');
        $to   = new \DateTimeImmutable('+1 day');

        $customer = $this->em->getRepository(Users::class)
            ->findOneBy(['email' => 'customer.test@proyecto-grado.test']);
        $this->assertNotNull($customer);

        $buyerIds = $this->repository->getDistinctBuyerIds($this->domain, $from, $to);

        $this->assertCount(1, $buyerIds);
        $this->assertSame((string) $customer->getId(), $buyerIds[0]);
    }

    public function testGetDistinctBuyerIdsReturnsEmptyOutsideDateRange(): void
    {
        $from = new \DateTimeImmutable('-200 days');
        $to   = new \DateTimeImmutable('-95 days');

        $this->assertSame([], $this->repository->getDistinctBuyerIds($this->domain, $from, $to));
    }

    public function testGetSalesByDayCountsOnlyThePaidFixtureOrders(): void
    {
        $from = new \DateTimeImmutable('-1 day');
        $to   = new \DateTimeImmutable('+1 day');

        $result = $this->repository->getSalesByDay($this->domain, $from, $to);

        $this->assertCount(1, $result);
        $this->assertSame(2, (int) $result[0]['orders']);
        $this->assertGreaterThan(0, (int) $result[0]['revenue']);
    }

    public function testGetSalesByCountryResolvesRealNameNotRawJson(): void
    {
        $from = new \DateTimeImmutable('-1 day');
        $to   = new \DateTimeImmutable('+1 day');

        $result = $this->repository->getSalesByCountry($this->domain, $from, $to, 'es');

        $this->assertCount(1, $result);
        $this->assertSame('Colombia', $result[0]['country']);
        $this->assertSame(2, $result[0]['orders']);
    }

    public function testGetSalesByRegionResolvesRealNameNotRawJson(): void
    {
        $from = new \DateTimeImmutable('-1 day');
        $to   = new \DateTimeImmutable('+1 day');

        $result = $this->repository->getSalesByRegion($this->domain, $from, $to, 'es');

        $this->assertCount(1, $result);
        $this->assertSame('Bogotá D.C.', $result[0]['region']);
    }

    public function testGetSalesByCityResolvesRealNameNotRawJson(): void
    {
        $from = new \DateTimeImmutable('-1 day');
        $to   = new \DateTimeImmutable('+1 day');

        $result = $this->repository->getSalesByCity($this->domain, $from, $to, 'es');

        $this->assertCount(1, $result);
        $this->assertSame('Bogotá', $result[0]['city']);
    }

    public function testGetAverageShippingTimeHoursReturnsNullWhenNoShippedOrdersInRange(): void
    {
        $from = new \DateTimeImmutable('-200 days');
        $to   = new \DateTimeImmutable('-95 days');

        $this->assertNull($this->repository->getAverageShippingTimeHours($this->domain, $from, $to));
    }

    public function testGetAverageShippingTimeHoursComputesRealDifference(): void
    {
        $from = new \DateTimeImmutable('-1 day');
        $to   = new \DateTimeImmutable('+1 day');

        $customer = $this->em->getRepository(Users::class)->findOneBy(['email' => 'customer.test@proyecto-grado.test']);
        $product  = $this->em->getRepository(\App\Entity\Products\Products::class)->findAll()[0];
        $country  = $this->em->getRepository(\App\Entity\Configurations\Globals\Countries::class)->findAll()[0];
        $region   = $this->em->getRepository(\App\Entity\Configurations\Globals\Regions::class)->findAll()[0];
        $city     = $this->em->getRepository(\App\Entity\Configurations\Globals\Cities::class)->findAll()[0];
        $shipped  = $this->em->getRepository(\App\Entity\Configurations\Globals\Status::class)->findOneBy(['name' => 'Enviado']);

        $order = new \App\Entity\Products\Orders\Orders();
        $order->add($customer, '10000', 'Calle sintética', $country, $region, $city, $shipped);
        $this->em->persist($order);
        $this->em->flush();

        $reflection = new \ReflectionClass($order);
        $createdAtProp = $reflection->getProperty('createdAt');
        $createdAtProp->setValue($order, new \DateTimeImmutable('-12 hours'));
        $this->em->persist($order);
        $this->em->flush();

        $orderProduct = (new \App\Entity\Products\Orders\OrdersProducts())->add($order, $product, '1', '10000', '10000');
        $this->em->persist($orderProduct);
        $this->em->flush();

        $avgHours = $this->repository->getAverageShippingTimeHours($this->domain, $from, $to);

        $this->assertNotNull($avgHours);
        $this->assertGreaterThan(0, $avgHours);
        $this->assertLessThanOrEqual(13, $avgHours);
    }
}
