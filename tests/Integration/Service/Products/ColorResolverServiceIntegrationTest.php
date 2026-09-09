<?php

namespace App\Tests\Integration\Service\Products;

use App\Entity\Products\Colors\Colors;
use App\Entity\Tenants\Domains\Domains;
use App\Service\Products\ColorResolverInterface;
use App\Service\Products\ColorResolverService;
use App\Tests\Integration\IntegrationTestCase;

class ColorResolverServiceIntegrationTest extends IntegrationTestCase
{
    public function testServiceIsRegisteredInContainerViaItsInterface(): void
    {
        $this->assertInstanceOf(
            ColorResolverService::class,
            static::getContainer()->get(ColorResolverInterface::class),
        );
    }

    public function testResolveReusesExistingColorByNameCaseInsensitive(): void
    {
        $domain = $this->em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8060']);
        $countBefore = count($this->em->getRepository(Colors::class)->findAll());

        $resolver = static::getContainer()->get(ColorResolverInterface::class);
        $result   = $resolver->resolve($domain, '  NEGRO  ', '#000000');

        $this->assertSame('Negro', $result->getName());

        $this->assertSame('#1a1a1a', $result->getHexCode());
        $this->assertCount($countBefore, $this->em->getRepository(Colors::class)->findAll());
    }

    public function testResolveCreatesNewColorWhenNameDoesNotExistYet(): void
    {
        $domain = $this->em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8060']);

        $resolver = static::getContainer()->get(ColorResolverInterface::class);
        $result   = $resolver->resolve($domain, 'Dorado', '#d4af37');

        $created = $this->em->getRepository(Colors::class)->findOneBy(['name' => 'Dorado']);
        $this->assertNotNull($created, 'La entidad debe persistirse en la BD');
        $this->assertSame((string) $created->getId(), (string) $result->getId());
        $this->assertSame('#d4af37', $result->getHexCode());
        $this->assertTrue($result->isActive());
    }

    public function testResolveScopesReuseByDomain(): void
    {

        $otherDomain = $this->em->getRepository(Domains::class)->findOneBy(['domain' => 'http://onurix.local:8061']);

        $resolver = static::getContainer()->get(ColorResolverInterface::class);
        $result   = $resolver->resolve($otherDomain, 'Negro', '#111111');

        $this->assertSame((string) $otherDomain->getId(), (string) $result->getDomain()->getId());
        $this->assertSame('#111111', $result->getHexCode());
    }
}
