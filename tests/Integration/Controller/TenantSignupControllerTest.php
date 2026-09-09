<?php

namespace App\Tests\Integration\Controller;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TenantSignupControllerTest extends WebTestCase
{
    private const LOCALE = 'es';

    private function bootWithFixtures(): KernelBrowser
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($em);
        $classes = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);

        $loader = static::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($em, new ORMPurger($em)))->execute($loader->getFixtures());

        return $client;
    }

    public function testComenzarIsAvailableOnPrincipalTenantDomain(): void
    {
        $client = $this->bootWithFixtures();

        $client->followRedirects();

        $client->request('GET', sprintf('http://localhost:8060/%s/comenzar', self::LOCALE));

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('/comenzar', $client->getRequest()->getUri());
    }

    public function testComenzarRedirectsToStoreOnNonPrincipalTenantDomain(): void
    {
        $client = $this->bootWithFixtures();
        $client->followRedirects();

        $client->request('GET', sprintf('http://localhost:8090/%s/comenzar', self::LOCALE));

        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('/comenzar', $client->getRequest()->getUri());
    }
}
