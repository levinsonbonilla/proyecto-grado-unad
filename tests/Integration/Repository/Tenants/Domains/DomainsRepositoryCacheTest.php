<?php

namespace App\Tests\Integration\Repository\Tenants\Domains;

use App\ArgumentHandler\DomainsArgument;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\Tests\Integration\IntegrationTestCase;

class DomainsRepositoryCacheTest extends IntegrationTestCase
{
    private function repository(): DomainsRepository
    {
        return static::getContainer()->get(DomainsRepository::class);
    }

    public function testInvalidateForcesFreshReadAfterNewDomainIsAdded(): void
    {
        $repository = $this->repository();
        $tenant = $this->em->getRepository(Tenants::class)->findOneBy(['name' => 'proyecto-grado-unad']);
        $this->assertNotNull($tenant);

        $before = $repository->getActiveDomainsByTenant($tenant);
        $this->assertCount(2, $before);

        $newDomain = new Domains();
        $newDomain->add(new DomainsArgument([
            'domain' => 'http://tercer-dominio-cache-test.example',
            'logo' => '',
            'notificationEmail' => 'noti@example.com',
            'supportEmail' => 'soporte@example.com',
        ], $tenant));
        $this->em->persist($newDomain);
        $this->em->flush();

        $stillCached = $repository->getActiveDomainsByTenant($tenant);
        $this->assertCount(2, $stillCached, 'sin invalidar, debe seguir sirviendo el snapshot cacheado');

        $repository->invalidateActiveDomainsByTenantCache($tenant);

        $after = $repository->getActiveDomainsByTenant($tenant);
        $this->assertCount(3, $after, 'tras invalidar, debe reflejar el dominio nuevo');
        $domainStrings = array_map(fn (Domains $d) => $d->getDomain(), $after);
        $this->assertContains('http://tercer-dominio-cache-test.example', $domainStrings);
    }

    public function testInvalidateForcesFreshReadAfterDomainIsDeactivated(): void
    {
        $repository = $this->repository();
        $tenant = $this->em->getRepository(Tenants::class)->findOneBy(['name' => 'proyecto-grado-unad']);
        $domain = $this->em->getRepository(Domains::class)->findOneBy(['domain' => 'http://onurix.local:8061']);
        $this->assertNotNull($domain);

        $this->assertCount(2, $repository->getActiveDomainsByTenant($tenant));

        $domain->deactivate();
        $this->em->flush();
        $repository->invalidateActiveDomainsByTenantCache($tenant);

        $after = $repository->getActiveDomainsByTenant($tenant);
        $this->assertCount(1, $after, 'el dominio desactivado ya no debe aparecer tras invalidar');
    }
}
