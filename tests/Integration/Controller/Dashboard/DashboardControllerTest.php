<?php

namespace App\Tests\Integration\Controller\Dashboard;

use App\Repository\Users\UsersRepository;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardControllerTest extends WebTestCase
{
    private const LOCALE = 'es';

    private function bootWithFixtures(): array
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($em);
        $classes = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);

        $loader = static::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($em, new ORMPurger($em)))->execute($loader->getFixtures());

        return [$client, $em];
    }

    public function testProfileRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->followRedirects();

        $client->request('GET', sprintf('/%s/dashboard/profile', self::LOCALE));

        $this->assertStringContainsString('login', $client->getRequest()->getUri());
    }

    public function testProfileRedirectsToCanonicalUsersProfileRoute(): void
    {
        [$client] = $this->bootWithFixtures();

        $admin = static::getContainer()->get(UsersRepository::class)
            ->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $this->assertNotNull($admin, 'fixture de admin.test@proyecto-grado.test debe existir');

        $client->loginUser($admin);

        $client->request('GET', 'http://localhost:8060/');
        $client->request('GET', sprintf('http://localhost:8060/%s/dashboard/profile', self::LOCALE));

        $this->assertResponseRedirects(sprintf('/%s/dashboard/users/profile', self::LOCALE));
    }

    public function testProfileRedirectEndsUpOnTheRealProfileForm(): void
    {
        [$client] = $this->bootWithFixtures();

        $admin = static::getContainer()->get(UsersRepository::class)
            ->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $this->assertNotNull($admin);

        $client->loginUser($admin);
        $client->request('GET', 'http://localhost:8060/');
        $client->followRedirects();
        $client->request('GET', sprintf('http://localhost:8060/%s/dashboard/profile', self::LOCALE));

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString(
            sprintf('/%s/dashboard/users/profile', self::LOCALE),
            $client->getRequest()->getUri()
        );
    }
}
