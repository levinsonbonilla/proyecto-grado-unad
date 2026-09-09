<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Tenants\Domains\Domains;
use App\Repository\Products\ProductsRepository;
use App\Tests\Integration\IntegrationTestCase;

class ProductsRepositoryTest extends IntegrationTestCase
{
    private ProductsRepository $repository;
    private Domains $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = static::getContainer()->get(ProductsRepository::class);
        $this->domain = $this->em->getRepository(Domains::class)
            ->findOneBy(['domain' => 'http://localhost:8060', 'active' => true]);

        $this->assertNotNull($this->domain);
    }

    public function testSearchPublicExcludesOutOfStockProduct(): void
    {
        $result = $this->repository->searchPublic(
            domain: $this->domain,
            allowedProductIds: null,
            categoryId: null,
            search: null,
            minPrice: null,
            maxPrice: null,
            page: 1,
            limit: 50,
        );

        $names = array_column($result['items'], 'name');

        $this->assertContains('Producto Test A', $names);
        $this->assertContains('Producto Test B', $names);
        $this->assertContains('Producto Test C (con stock)', $names);
        $this->assertNotContains('Producto Test D (agotado)', $names, 'un producto con stock=0 no debe aparecer en la tienda pública');
    }

    public function testSearchPublicExposesStockValueForTrackedProduct(): void
    {
        $result = $this->repository->searchPublic(
            domain: $this->domain,
            allowedProductIds: null,
            categoryId: null,
            search: 'Producto Test C',
            minPrice: null,
            maxPrice: null,
            page: 1,
            limit: 50,
        );

        $this->assertCount(1, $result['items']);
        $item = reset($result['items']);
        $this->assertSame(5, $item['stock']);
    }

    public function testSearchPublicLeavesStockNullForUntrackedProduct(): void
    {
        $result = $this->repository->searchPublic(
            domain: $this->domain,
            allowedProductIds: null,
            categoryId: null,
            search: 'Producto Test A',
            minPrice: null,
            maxPrice: null,
            page: 1,
            limit: 50,
        );

        $this->assertCount(1, $result['items']);
        $item = reset($result['items']);
        $this->assertNull($item['stock']);
    }

    public function testGetPublicHomeListExcludesOutOfStockProduct(): void
    {
        $result = $this->repository->getPublicHomeList($this->domain, 50);

        $names = array_column($result, 'name');

        $this->assertContains('Producto Test C (con stock)', $names);
        $this->assertNotContains('Producto Test D (agotado)', $names);
    }
}
