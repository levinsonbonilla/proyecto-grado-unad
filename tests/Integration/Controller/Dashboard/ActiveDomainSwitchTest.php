<?php

namespace App\Tests\Integration\Controller\Dashboard;

use App\ArgumentHandler\DomainsArgument;
use App\ArgumentHandler\UsersDomainsArgument;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\UsersDomains;
use App\Handler\Configuration\ActiveDashboardDomainResolver;
use App\Repository\Tenants\TenantsRepository;
use App\Repository\Users\UsersRepository;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ActiveDomainSwitchTest extends WebTestCase
{
    private const LOCALE = 'es';

    private function bootWithFixtures(): array
    {

        self::ensureKernelShutdown();
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

    private function requestFollowing(KernelBrowser $client, string $url): void
    {

        $client->followRedirects();
        $client->request('GET', $url);
        $client->followRedirects(false);
    }

    private function grantSecondDomain(EntityManagerInterface $em): Domains
    {
        $usersRepository = static::getContainer()->get(UsersRepository::class);
        $tenantsRepository = static::getContainer()->get(TenantsRepository::class);

        $admin = $usersRepository->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $tenant = $tenantsRepository->findOneBy(['name' => 'proyecto-grado-unad']);

        $secondDomain = new Domains();
        $secondDomain->add(new DomainsArgument([
            'domain' => 'http://segundo-dominio-admin-switch.test',
            'logo' => '',
            'notificationEmail' => 'noti@example.com',
            'supportEmail' => 'soporte@example.com',
        ], $tenant));
        $em->persist($secondDomain);
        $em->flush();

        $membership = new UsersDomains();
        $membership->add(new UsersDomainsArgument([
            'user' => $admin,
            'domain' => $secondDomain,
            'roles' => ['ROLE_ADMIN'],
        ]));
        $em->persist($membership);
        $em->flush();

        return $secondDomain;
    }

    public function testAdminWithMultipleDomainsGetsForcedSelectionThenCanSwitch(): void
    {
        [$client, $em] = $this->bootWithFixtures();
        $secondDomain = $this->grantSecondDomain($em);

        $admin = static::getContainer()->get(UsersRepository::class)->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $client->loginUser($admin);

        $this->requestFollowing($client, sprintf('http://localhost:8060/%s/dashboard', self::LOCALE));

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString(
            'segundo-dominio-admin-switch.test',
            (string) $client->getResponse()->getContent(),
            'La pantalla de selección debería listar ambos dominios del admin.'
        );

        $client->request(
            'GET',
            sprintf('http://localhost:8060/%s/dashboard/domain/%s/switch', self::LOCALE, $secondDomain->getId())
        );
        $this->assertResponseRedirects();

        $this->requestFollowing($client, sprintf('http://localhost:8060/%s/dashboard', self::LOCALE));
        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString(
            'Elige tu dominio de trabajo',
            (string) $client->getResponse()->getContent()
        );
    }

    public function testSwitchingToDomainNotOwnedByUserIsForbidden(): void
    {
        [$client, $em] = $this->bootWithFixtures();

        $foreignDomain = static::getContainer()->get(\App\Repository\Tenants\Domains\DomainsRepository::class)
            ->findOneBy(['domain' => 'http://localhost:8090']);
        $this->assertNotNull($foreignDomain);

        $admin = static::getContainer()->get(UsersRepository::class)->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $client->loginUser($admin);

        $this->requestFollowing(
            $client,
            sprintf('http://localhost:8060/%s/dashboard/domain/%s/switch', self::LOCALE, $foreignDomain->getId())
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminWithExactlyOneDomainIsNotInterrupted(): void
    {
        [$client] = $this->bootWithFixtures();

        $admin = static::getContainer()->get(UsersRepository::class)->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $client->loginUser($admin);

        $this->requestFollowing($client, sprintf('http://localhost:8060/%s/dashboard', self::LOCALE));

        $this->assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        $this->assertStringNotContainsString('Elige tu dominio de trabajo', $content);

        $this->assertStringContainsString('fa-globe', $content);
        $this->assertStringContainsString('localhost:8060', $content);
    }

    public function testSuperAdminGetsForcedSelectionScreenScopedToCurrentTenantOnly(): void
    {
        [$client] = $this->bootWithFixtures();

        $superAdmin = static::getContainer()->get(UsersRepository::class)->findOneBy(['email' => 'levinson.bonilla22@gmail.com']);
        $this->assertNotNull($superAdmin);
        $client->loginUser($superAdmin);

        $this->requestFollowing($client, sprintf('http://localhost:8060/%s/dashboard', self::LOCALE));

        $this->assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('Elige tu dominio de trabajo', $content);
        $this->assertStringContainsString('onurix.local:8061', $content, 'debe listar el otro dominio del MISMO tenant (proyecto-grado-unad)');
        $this->assertStringNotContainsString('localhost:8090', $content, 'no debe listar el dominio de un tenant distinto');

        $foreignDomain = static::getContainer()->get(\App\Repository\Tenants\Domains\DomainsRepository::class)
            ->findOneBy(['domain' => 'http://localhost:8090']);
        $this->assertNotNull($foreignDomain);

        $client->request(
            'GET',
            sprintf('http://localhost:8060/%s/dashboard/domain/%s/switch', self::LOCALE, $foreignDomain->getId())
        );
        $this->assertResponseStatusCodeSame(403);
    }

    public function testSuperAdminCanSwitchToAnotherDomainOfTheSameTenantAndSeesGlobeIconAfter(): void
    {
        [$client] = $this->bootWithFixtures();

        $superAdmin = static::getContainer()->get(UsersRepository::class)->findOneBy(['email' => 'levinson.bonilla22@gmail.com']);
        $client->loginUser($superAdmin);

        $sameTenantDomain = static::getContainer()->get(\App\Repository\Tenants\Domains\DomainsRepository::class)
            ->findOneBy(['domain' => 'http://onurix.local:8061']);
        $this->assertNotNull($sameTenantDomain);

        $this->requestFollowing($client, sprintf('http://localhost:8060/%s/dashboard', self::LOCALE));
        $this->assertStringContainsString('Elige tu dominio de trabajo', (string) $client->getResponse()->getContent());

        $client->request(
            'GET',
            sprintf('http://localhost:8060/%s/dashboard/domain/%s/switch', self::LOCALE, $sameTenantDomain->getId())
        );
        $this->assertResponseRedirects();

        $this->requestFollowing($client, sprintf('http://localhost:8060/%s/dashboard', self::LOCALE));
        $content = (string) $client->getResponse()->getContent();
        $this->assertStringNotContainsString('Elige tu dominio de trabajo', $content);
        $this->assertStringContainsString('fa-globe', $content);
        $this->assertStringContainsString('onurix.local:8061', $content);
    }
}
