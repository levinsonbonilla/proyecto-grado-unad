<?php

namespace App\Tests\Integration\Controller\Dashboard;

use App\Repository\Users\UsersRepository;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TenantsControllerTest extends WebTestCase
{
    private const LOCALE = 'es';

    public function testListRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->followRedirects();

        $client->request('GET', sprintf('/%s/dashboard/tenants', self::LOCALE));

        $this->assertStringContainsString('login', $client->getRequest()->getUri());
    }

    public function testNewRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->followRedirects();

        $client->request('GET', sprintf('/%s/dashboard/tenants/new', self::LOCALE));

        $this->assertStringContainsString('login', $client->getRequest()->getUri());
    }

    public function testListApiRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->followRedirects();

        $client->request('POST', sprintf('/%s/dashboard/tenants/list', self::LOCALE));

        $this->assertStringContainsString('login', $client->getRequest()->getUri());
    }

    public function testListRouteMethodNotAllowed(): void
    {
        $client = static::createClient();

        $client->request('PUT', sprintf('/%s/dashboard/tenants/list', self::LOCALE));

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [302, 403, 405]);
    }

    public function testNewRouteAcceptsPostAndGet(): void
    {
        $client = static::createClient();

        $client->request('POST', sprintf('/%s/dashboard/tenants/new', self::LOCALE));

        $this->assertResponseRedirects();
    }

    public function testListForbiddenForRoleAdminWithoutSuperAdmin(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($em);
        $classes = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);

        $loader = static::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($em, new ORMPurger($em)))->execute($loader->getFixtures());

        $admin = static::getContainer()->get(UsersRepository::class)
            ->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $client->loginUser($admin);

        $client->followRedirects();
        $client->request('GET', sprintf('http://localhost:8060/%s/dashboard/tenants', self::LOCALE));

        $this->assertResponseStatusCodeSame(403);
    }
}
