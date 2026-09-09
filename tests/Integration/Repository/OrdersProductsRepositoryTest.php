<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Tenants\Domains\Domains;
use App\Repository\Products\Orders\OrdersProductsRepository;
use App\Tests\Integration\IntegrationTestCase;

class OrdersProductsRepositoryTest extends IntegrationTestCase
{
    private OrdersProductsRepository $repository;
    private Domains $domain;
    private \DateTimeImmutable $from;
    private \DateTimeImmutable $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = static::getContainer()->get(OrdersProductsRepository::class);
        $this->domain = $this->em->getRepository(Domains::class)
            ->findOneBy(['domain' => 'http://localhost:8060', 'active' => true]);
        $this->assertNotNull($this->domain);

        $this->from = new \DateTimeImmutable('-1 day');
        $this->to   = new \DateTimeImmutable('+1 day');
    }

    public function testGetTopSellingProductsCountsOnlyPaidOrderLines(): void
    {
        $result = $this->repository->getTopSellingProducts($this->domain, $this->from, $this->to);

        $this->assertCount(1, $result);
        $this->assertSame('Producto Test A', $result[0]['product']);

        $this->assertSame(4, (int) $result[0]['unitsSold']);
        $this->assertGreaterThan(0, (int) $result[0]['revenue']);
    }

    public function testGetTopSellingProductsReturnsEmptyOutsideDateRange(): void
    {
        $from = new \DateTimeImmutable('-200 days');
        $to   = new \DateTimeImmutable('-95 days');

        $this->assertSame([], $this->repository->getTopSellingProducts($this->domain, $from, $to));
    }

    public function testGetTopSellingProductsRespectsLimit(): void
    {
        $result = $this->repository->getTopSellingProducts($this->domain, $this->from, $this->to, limit: 1);

        $this->assertLessThanOrEqual(1, count($result));
    }
}
