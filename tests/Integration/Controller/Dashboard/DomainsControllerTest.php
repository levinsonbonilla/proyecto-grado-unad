<?php

namespace App\Tests\Integration\Controller\Dashboard;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\Repository\Tenants\TenantsRepository;
use App\Repository\Users\UsersRepository;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DomainsControllerTest extends WebTestCase
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

    private function submitDomainForm(KernelBrowser $client, string $url, string $domainValue, ?string $name = null): void
    {

        $client->followRedirects();
        $crawler = $client->request('GET', $url);
        $client->followRedirects(false);
        $fields = [
            'domains[domain]' => $domainValue,
            'domains[notificationEmail]' => 'noti@example.com',
            'domains[supportEmail]' => 'soporte@example.com',
        ];
        if ($name !== null) {
            $fields['domains[name]'] = $name;
        }
        $form = $crawler->selectButton('send')->form($fields);

        $tmpFile = tempnam(sys_get_temp_dir(), 'logo') . '.png';
        file_put_contents($tmpFile, 'fake-image-content');
        $form['domains[image]']->upload($tmpFile);

        $client->submit($form);
    }

    public function testRegularAdminCreatingOwnDomainGetsImmediateAccess(): void
    {
        [$client, $em] = $this->bootWithFixtures();

        $admin = static::getContainer()->get(UsersRepository::class)
            ->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $client->loginUser($admin);

        $newDomainUrl = 'http://segundo-dominio-admin.test';
        $this->submitDomainForm(
            $client,
            sprintf('http://localhost:8060/%s/dashboard/configurations/domains/new', self::LOCALE),
            $newDomainUrl
        );

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('app-toast-success', (string) $client->getResponse()->getContent());

        $em->clear();
        $newDomain = static::getContainer()->get(DomainsRepository::class)->findOneBy(['domain' => $newDomainUrl]);
        $this->assertNotNull($newDomain);

        $adminReloaded = static::getContainer()->get(UsersRepository::class)
            ->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $membership = null;
        foreach ($adminReloaded->getUserDomainsActives() as $usersDomains) {
            if ((string) $usersDomains->getDomain()->getId() === (string) $newDomain->getId()) {
                $membership = $usersDomains;
                break;
            }
        }

        $this->assertNotNull($membership, 'El admin debería tener UsersDomains en el dominio que acaba de crear.');
        $this->assertSame(['ROLE_ADMIN'], $membership->getRoles());
    }

    public function testSuperAdminOnboardingCreatesDomainForExplicitTenant(): void
    {
        [$client, $em] = $this->bootWithFixtures();

        $superAdmin = static::getContainer()->get(UsersRepository::class)
            ->findOneBy(['email' => 'levinson.bonilla22@gmail.com']);
        $targetTenant = static::getContainer()->get(TenantsRepository::class)
            ->findOneBy(['name' => 'tenant-demo-2']);
        $this->assertInstanceOf(Tenants::class, $targetTenant);

        $client->loginUser($superAdmin);

        $newDomainUrl = 'http://tercer-dominio-onboarding.test';
        $this->submitDomainForm(
            $client,
            sprintf('http://localhost:8060/%s/dashboard/tenants/%s/domains/new', self::LOCALE, $targetTenant->getId()),
            $newDomainUrl
        );

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('app-toast-success', (string) $client->getResponse()->getContent());

        $em->clear();
        $newDomain = static::getContainer()->get(DomainsRepository::class)->findOneBy(['domain' => $newDomainUrl]);
        $this->assertInstanceOf(Domains::class, $newDomain);
        $this->assertSame((string) $targetTenant->getId(), (string) $newDomain->getTenant()->getId());

        $superAdminReloaded = static::getContainer()->get(UsersRepository::class)
            ->findOneBy(['email' => 'levinson.bonilla22@gmail.com']);
        foreach ($superAdminReloaded->getUserDomainsActives() as $usersDomains) {
            $this->assertNotSame(
                (string) $newDomain->getId(),
                (string) $usersDomains->getDomain()->getId(),
                'El superadmin no debería auto-provisionarse UsersDomains en un dominio ajeno.'
            );
        }
    }

    public function testDomainWithNameAppearsInActiveDomainSelector(): void
    {
        [$client, $em] = $this->bootWithFixtures();

        $admin = static::getContainer()->get(UsersRepository::class)
            ->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $client->loginUser($admin);

        $newDomainUrl = 'http://un-hostname-bastante-largo-para-el-selector.test';
        $this->submitDomainForm(
            $client,
            sprintf('http://localhost:8060/%s/dashboard/configurations/domains/new', self::LOCALE),
            $newDomainUrl,
            'Tienda Elegante'
        );
        $this->assertResponseIsSuccessful();

        $em->clear();
        $newDomain = static::getContainer()->get(DomainsRepository::class)->findOneBy(['domain' => $newDomainUrl]);
        $this->assertNotNull($newDomain);
        $this->assertSame('Tienda Elegante', $newDomain->getName());
        $this->assertSame('Tienda Elegante', $newDomain->getDisplayName());

        $client->followRedirects();
        $client->request('GET', sprintf('http://localhost:8060/%s/dashboard', self::LOCALE));
        $content = (string) $client->getResponse()->getContent();

        $this->assertStringContainsString('Tienda Elegante', $content);
        $this->assertStringNotContainsString('>' . $newDomainUrl . '<', $content);
    }

    public function testSuperAdminListEndpointReturnsAllDomainsWithTenantName(): void
    {
        [$client] = $this->bootWithFixtures();

        $superAdmin = static::getContainer()->get(UsersRepository::class)
            ->findOneBy(['email' => 'levinson.bonilla22@gmail.com']);
        $client->loginUser($superAdmin);

        $client->request('GET', 'http://localhost:8060/es/dashboard');
        $client->request('GET', sprintf('http://localhost:8060/%s/dashboard/configurations/domains/list', self::LOCALE));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertCount(3, $data['data']);
        foreach ($data['data'] as $row) {
            $this->assertArrayHasKey('tenantName', $row);
            $this->assertArrayHasKey('domainName', $row);
        }
    }

    public function testRegularAdminListEndpointReturnsOnlyOwnTenantDomainsWithoutTenantName(): void
    {
        [$client] = $this->bootWithFixtures();

        $admin = static::getContainer()->get(UsersRepository::class)
            ->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $client->loginUser($admin);

        $client->request('GET', 'http://localhost:8060/es/dashboard');
        $client->request('GET', sprintf('http://localhost:8060/%s/dashboard/configurations/domains/list', self::LOCALE));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertCount(2, $data['data']);
        foreach ($data['data'] as $row) {
            $this->assertArrayNotHasKey('tenantName', $row);
            $this->assertArrayHasKey('domainName', $row);
        }
    }
}
