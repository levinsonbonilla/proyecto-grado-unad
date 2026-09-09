<?php

namespace App\Tests\Integration\Controller\Dashboard;

use App\ArgumentHandler\UsersArgument;
use App\ArgumentHandler\UsersDomainsArgument;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Entity\Users\UsersDomains;
use App\Repository\Users\UsersRepository;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UsersControllerTest extends WebTestCase
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

    public function testNewRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->followRedirects();

        $client->request('GET', sprintf('/%s/dashboard/users/new', self::LOCALE));

        $this->assertStringContainsString('login', $client->getRequest()->getUri());
    }

    public function testNewFormLoadsSuccessfullyForRegularAdmin(): void
    {
        [$client] = $this->bootWithFixtures();

        $admin = static::getContainer()->get(UsersRepository::class)
            ->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $this->assertNotNull($admin, 'fixture de admin.test@proyecto-grado.test debe existir');

        $client->loginUser($admin);
        $client->followRedirects();
        $client->request('GET', sprintf('http://localhost:8060/%s/dashboard/users/new', self::LOCALE));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#add_user_form');

        $this->assertSelectorNotExists('#users_tenant');
    }

    public function testEditFormLoadsSuccessfullyForRegularAdmin(): void
    {
        [$client] = $this->bootWithFixtures();

        $usersRepository = static::getContainer()->get(UsersRepository::class);
        $admin = $usersRepository->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $customer = $usersRepository->findOneBy(['email' => 'customer.test@proyecto-grado.test']);
        $this->assertNotNull($admin);
        $this->assertNotNull($customer);

        $client->loginUser($admin);
        $client->followRedirects();
        $client->request('GET', sprintf('http://localhost:8060/%s/dashboard/users/%s/edit', self::LOCALE, $customer->getId()));

        $this->assertResponseIsSuccessful();
    }

    public function testNewFormLoadsSuccessfullyForSuperAdminWithTenantSelector(): void
    {
        [$client] = $this->bootWithFixtures();

        $superAdmin = static::getContainer()->get(UsersRepository::class)
            ->findOneBy(['email' => 'levinson.bonilla22@gmail.com']);
        $this->assertNotNull($superAdmin);

        $client->loginUser($superAdmin);
        $client->followRedirects();
        $client->request('GET', sprintf('http://localhost:8060/%s/dashboard/users/new', self::LOCALE));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#users_tenant');
        $this->assertSelectorExists('#users_domain');
    }

    public function testSuperAdminCanCreateAdminUserForChildTenant(): void
    {
        [$client, $em] = $this->bootWithFixtures();

        $superAdmin = static::getContainer()->get(UsersRepository::class)
            ->findOneBy(['email' => 'levinson.bonilla22@gmail.com']);
        $childDomain = $em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8090']);
        $this->assertNotNull($childDomain, 'fixture del dominio del tenant hijo (localhost:8090) debe existir');
        $childTenant = $childDomain->getTenant();

        $client->loginUser($superAdmin);
        $client->followRedirects();
        $crawler = $client->request('GET', sprintf('http://localhost:8060/%s/dashboard/users/new', self::LOCALE));
        $this->assertResponseIsSuccessful();

        $email = 'qa.regresion.hijo.' . uniqid() . '@proyecto-grado.test';

        $form = $crawler->filter('#add_user_form')->form([
            'users[email]' => $email,
            'users[roles]' => 'ROLE_ADMIN',
            'users[name]' => 'QA',
            'users[lastName]' => 'Hijo',
            'users[tenant]' => (string) $childTenant->getId(),
        ]);
        $values = $form->getPhpValues();
        $values['users']['domain'] = (string) $childDomain->getId();

        $client->request($form->getMethod(), $form->getUri(), $values);
        $this->assertResponseIsSuccessful();

        $created = static::getContainer()->get(UsersRepository::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($created, 'el usuario debió crearse');
        $this->assertSame(['ROLE_ADMIN'], $this->rolesWithoutDefault($created));
        $this->assertSame('http://localhost:8090', $created->getDomain()->getDomain());
        $this->assertSame('tenant-demo-2', $created->getDomain()->getTenant()->getName());
    }

    public function testUserListIsScopedPerTenantDomain(): void
    {
        [, $em] = $this->bootWithFixtures();

        $mainDomainUsers = $em->getRepository(\App\Entity\Users\UsersDomains::class)->findBy([]);
        $emailsOnMainDomain = [];
        $emailsOnChildDomain = [];
        foreach ($mainDomainUsers as $ud) {
            if ($ud->getDomain()->getDomain() === 'http://localhost:8060') {
                $emailsOnMainDomain[] = $ud->getUser()->getEmail();
            }
            if ($ud->getDomain()->getDomain() === 'http://localhost:8090') {
                $emailsOnChildDomain[] = $ud->getUser()->getEmail();
            }
        }

        $this->assertContains('admin.test@proyecto-grado.test', $emailsOnMainDomain);
        $this->assertNotContains('admin.test@proyecto-grado.test', $emailsOnChildDomain);
    }

    public function testSuperAdminCanChangeAnotherUsersRole(): void
    {
        [$client, $em] = $this->bootWithFixtures();

        $usersRepository = static::getContainer()->get(UsersRepository::class);
        $superAdmin = $usersRepository->findOneBy(['email' => 'levinson.bonilla22@gmail.com']);
        $customer   = $usersRepository->findOneBy(['email' => 'customer.test@proyecto-grado.test']);
        $this->assertSame(['ROLE_USER'], $this->rolesWithoutDefault($customer) ?: ['ROLE_USER']);

        $client->loginUser($superAdmin);
        $client->followRedirects();
        $crawler = $client->request('GET', sprintf('http://localhost:8060/%s/dashboard/users/%s/edit', self::LOCALE, $customer->getId()));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#users_roles', 'el campo roles debe estar presente al editar');

        $form = $crawler->filter('#add_user_form')->form();
        $form['users[roles]'] = 'ROLE_ADMIN';
        $form['users[validatedEmail]'] = '1';
        $form['users[activated]'] = '1';
        $client->submit($form);
        $this->assertResponseIsSuccessful();

        $em->clear();
        $updated = static::getContainer()->get(UsersRepository::class)->findOneBy(['email' => 'customer.test@proyecto-grado.test']);
        $this->assertSame(['ROLE_ADMIN'], $this->rolesWithoutDefault($updated));

        $usersDomains = $em->getRepository(UsersDomains::class)->findOneBy(['user' => $updated]);
        $this->assertSame(['ROLE_ADMIN'], $usersDomains->getRoles(), 'UsersDomains.roles del dominio propio también debe quedar sincronizado');
    }

    public function testUserCannotChangeOwnRole(): void
    {
        [$client, $em] = $this->bootWithFixtures();

        $superAdmin = static::getContainer()->get(UsersRepository::class)
            ->findOneBy(['email' => 'levinson.bonilla22@gmail.com']);

        $client->loginUser($superAdmin);
        $client->followRedirects();
        $crawler = $client->request('GET', sprintf('http://localhost:8060/%s/dashboard/users/%s/edit', self::LOCALE, $superAdmin->getId()));

        $form = $crawler->filter('#add_user_form')->form();
        $form['users[roles]'] = 'ROLE_ADMIN';
        $form['users[validatedEmail]'] = '1';
        $form['users[activated]'] = '1';
        $client->submit($form);
        $this->assertResponseIsSuccessful();

        $em->clear();
        $reloaded = static::getContainer()->get(UsersRepository::class)->findOneBy(['email' => 'levinson.bonilla22@gmail.com']);
        $this->assertSame(['ROLE_SUPER_ADMIN'], $this->rolesWithoutDefault($reloaded), 'el rol propio no debió cambiar');
    }

    public function testCannotDemoteLastActiveSuperAdminButCanDemoteOthers(): void
    {
        [$client, $em] = $this->bootWithFixtures();

        $mainDomain = $em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8060']);
        $extraSuperAdmin = $this->createUser($em, 'extra.super@proyecto-grado.test', ['ROLE_SUPER_ADMIN'], $mainDomain);

        $usersRepository = static::getContainer()->get(UsersRepository::class);
        $admin = $usersRepository->findOneBy(['email' => 'admin.test@proyecto-grado.test']);

        $client->loginUser($admin);
        $client->followRedirects();

        $crawler = $client->request('GET', sprintf('http://localhost:8060/%s/dashboard/users/%s/edit', self::LOCALE, $extraSuperAdmin->getId()));
        $form = $crawler->filter('#add_user_form')->form();
        $form['users[roles]'] = 'ROLE_ADMIN';
        $form['users[validatedEmail]'] = '1';
        $form['users[activated]'] = '1';
        $client->submit($form);
        $this->assertResponseIsSuccessful();

        $em->clear();
        $reloadedExtra = $usersRepository->findOneBy(['email' => 'extra.super@proyecto-grado.test']);
        $this->assertSame(['ROLE_ADMIN'], $this->rolesWithoutDefault($reloadedExtra), 'con otro super admin activo, sí se puede degradar');

        $superAdmin = $usersRepository->findOneBy(['email' => 'levinson.bonilla22@gmail.com']);
        $crawler = $client->request('GET', sprintf('http://localhost:8060/%s/dashboard/users/%s/edit', self::LOCALE, $superAdmin->getId()));
        $form = $crawler->filter('#add_user_form')->form();
        $form['users[roles]'] = 'ROLE_ADMIN';
        $form['users[validatedEmail]'] = '1';
        $form['users[activated]'] = '1';
        $client->submit($form);
        $this->assertResponseIsSuccessful();

        $em->clear();
        $reloadedSuperAdmin = $usersRepository->findOneBy(['email' => 'levinson.bonilla22@gmail.com']);
        $this->assertSame(['ROLE_SUPER_ADMIN'], $this->rolesWithoutDefault($reloadedSuperAdmin), 'no debe quedar la plataforma sin ningún super admin');
    }

    public function testRegularAdminCannotEditUserFromAnotherTenant(): void
    {
        [$client, $em] = $this->bootWithFixtures();

        $childDomain = $em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8090']);
        $childUser = $this->createUser($em, 'child.tenant.user@proyecto-grado.test', ['ROLE_USER'], $childDomain);

        $admin = static::getContainer()->get(UsersRepository::class)->findOneBy(['email' => 'admin.test@proyecto-grado.test']);

        $client->loginUser($admin);
        $client->followRedirects();
        $client->request('GET', sprintf('http://localhost:8060/%s/dashboard/users/%s/edit', self::LOCALE, $childUser->getId()));

        $this->assertResponseStatusCodeSame(403);
    }

    private function createUser(EntityManagerInterface $em, string $email, array $roles, Domains $domain): Users
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new Users($hasher);
        $user->add(new UsersArgument([
            'email' => $email,
            'name' => 'QA',
            'lastName' => 'Fixture',
            'roles' => $roles,
            'password' => 'QaFixture1*',
        ], $domain));
        $em->persist($user);

        $usersDomains = new UsersDomains();
        $usersDomains->add(new UsersDomainsArgument([
            'user' => $user,
            'domain' => $domain,
            'roles' => $roles,
        ]));
        $em->persist($usersDomains);

        $em->flush();

        return $user;
    }

    private function rolesWithoutDefault(Users $user): array
    {
        return array_values(array_diff($user->getRoles(), ['ROLE_USER']));
    }
}
