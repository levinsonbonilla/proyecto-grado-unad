<?php

namespace App\Tests\Integration\Service\Products;

use App\Entity\Products\Medidas\Medidas;
use App\Entity\Tenants\Domains\Domains;
use App\Service\Products\MedidaResolverInterface;
use App\Service\Products\MedidaResolverService;
use App\Tests\Integration\IntegrationTestCase;

class MedidaResolverServiceIntegrationTest extends IntegrationTestCase
{
    public function testServiceIsRegisteredInContainerViaItsInterface(): void
    {
        $this->assertInstanceOf(
            MedidaResolverService::class,
            static::getContainer()->get(MedidaResolverInterface::class),
        );
    }

    public function testResolveReusesExistingMedidaByNameCaseInsensitive(): void
    {
        $domain = $this->em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8060']);
        $countBefore = count($this->em->getRepository(Medidas::class)->findAll());

        $resolver = static::getContainer()->get(MedidaResolverInterface::class);
        $result   = $resolver->resolve($domain, '  m  ');

        $this->assertSame('M', $result->getName());
        $this->assertCount($countBefore, $this->em->getRepository(Medidas::class)->findAll());
    }

    public function testResolveCreatesNewMedidaWhenNameDoesNotExistYet(): void
    {
        $domain = $this->em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8060']);

        $resolver = static::getContainer()->get(MedidaResolverInterface::class);
        $result   = $resolver->resolve($domain, 'XXL');

        $created = $this->em->getRepository(Medidas::class)->findOneBy(['name' => 'XXL']);
        $this->assertNotNull($created, 'La entidad debe persistirse en la BD');
        $this->assertSame((string) $created->getId(), (string) $result->getId());
        $this->assertTrue($result->isActive());
    }

    public function testResolveScopesReuseByDomain(): void
    {

        $otherDomain = $this->em->getRepository(Domains::class)->findOneBy(['domain' => 'http://onurix.local:8061']);

        $resolver = static::getContainer()->get(MedidaResolverInterface::class);
        $result   = $resolver->resolve($otherDomain, 'M');

        $this->assertSame((string) $otherDomain->getId(), (string) $result->getDomain()->getId());
    }
}
