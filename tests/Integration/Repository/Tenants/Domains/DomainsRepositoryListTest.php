<?php

namespace App\Tests\Integration\Repository\Tenants\Domains;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\Tests\Integration\IntegrationTestCase;

class DomainsRepositoryListTest extends IntegrationTestCase
{
    private DomainsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = static::getContainer()->get(DomainsRepository::class);
    }

    public function testNullTenantReturnsAllDomainsAcrossAllTenants(): void
    {
        $result = $this->repository->getDomainsList(null);

        $this->assertCount(3, $result);
    }

    public function testNullTenantIncludesTenantNameColumn(): void
    {
        $result = $this->repository->getDomainsList(null);

        foreach ($result as $row) {
            $this->assertArrayHasKey('tenantName', $row);
        }
        $tenantNames = array_column($result, 'tenantName');
        $this->assertContains('proyecto-grado-unad', $tenantNames);
        $this->assertContains('tenant-demo-2', $tenantNames);
    }

    public function testScopedToOneTenantReturnsOnlyItsDomains(): void
    {
        $tenant = $this->em->getRepository(Tenants::class)->findOneBy(['name' => 'proyecto-grado-unad']);
        $this->assertNotNull($tenant);

        $result = $this->repository->getDomainsList($tenant);

        $this->assertCount(2, $result);
        foreach ($result as $row) {
            $this->assertArrayNotHasKey('tenantName', $row, 'un tenant puntual no debe traer la columna de tenant');
        }
    }

    public function testDomainNameColumnReflectsTheShortNameField(): void
    {
        $domain = $this->em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8060']);
        $this->assertNotNull($domain);
        $reflection = new \ReflectionClass($domain);
        $nameProp = $reflection->getProperty('name');
        $nameProp->setValue($domain, 'Tienda Principal');
        $this->em->flush();

        $tenant = $this->em->getRepository(Tenants::class)->findOneBy(['name' => 'proyecto-grado-unad']);
        $result = $this->repository->getDomainsList($tenant);

        $row = array_values(array_filter($result, fn ($r) => $r['domain'] === 'http://localhost:8060'))[0] ?? null;
        $this->assertNotNull($row);
        $this->assertSame('Tienda Principal', $row['domainName']);
    }

    public function testCountMatchesForBothScopes(): void
    {
        $tenant = $this->em->getRepository(Tenants::class)->findOneBy(['name' => 'proyecto-grado-unad']);

        $totalAll = $this->repository->getDomainsList(null, true);
        $totalOneTenant = $this->repository->getDomainsList($tenant, true);

        $this->assertSame(3, (int) reset($totalAll));
        $this->assertSame(2, (int) reset($totalOneTenant));
    }
}
