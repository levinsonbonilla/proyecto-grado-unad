<?php

namespace App\Tests\Integration\Security;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Security\UserChecker;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class UserCheckerTest extends IntegrationTestCase
{
    private function pushRequestForDomain(string $domain): void
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create($domain . '/es/login'));
    }

    public function testSuperAdminCanLogInOnDomainWithoutOwnUsersDomainsMembership(): void
    {
        $superAdmin = $this->em->getRepository(Users::class)->findOneBy(['email' => 'levinson.bonilla22@gmail.com']);
        $this->assertNotNull($superAdmin, 'fixture del superadmin debe existir');

        $secondDomain = $this->em->getRepository(Domains::class)->findOneBy(['domain' => 'http://onurix.local:8061']);
        $this->assertNotNull($secondDomain, 'fixture del segundo domain debe existir');

        $this->pushRequestForDomain('http://onurix.local:8061');

        $checker = static::getContainer()->get(UserChecker::class);
        $checker->checkPreAuth($superAdmin);

        $this->addToAssertionCount(1);
    }

    public function testRegularAdminCannotLogInOnNonPrincipalDomainWithoutOwnUsersDomainsMembership(): void
    {
        $admin = $this->em->getRepository(Users::class)->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $this->assertNotNull($admin);

        $this->pushRequestForDomain('http://localhost:8090');

        $checker = static::getContainer()->get(UserChecker::class);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $checker->checkPreAuth($admin);
    }

    public function testRegularAdminWithAdminMembershipCanLogInOnAnyPrincipalTenantDomain(): void
    {
        $admin = $this->em->getRepository(Users::class)->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $this->assertNotNull($admin);

        $this->pushRequestForDomain('http://onurix.local:8061');

        $checker = static::getContainer()->get(UserChecker::class);
        $checker->checkPreAuth($admin);

        $this->addToAssertionCount(1);
    }

    public function testRegularUserCannotLogInOnPrincipalDomainEvenWithoutMembership(): void
    {
        $customer = $this->em->getRepository(Users::class)->findOneBy(['email' => 'customer.test@proyecto-grado.test']);
        $this->assertNotNull($customer);

        $this->pushRequestForDomain('http://onurix.local:8061');

        $checker = static::getContainer()->get(UserChecker::class);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $checker->checkPreAuth($customer);
    }

    public function testSuperAdminCanStillLogInOnItsOwnDomain(): void
    {
        $superAdmin = $this->em->getRepository(Users::class)->findOneBy(['email' => 'levinson.bonilla22@gmail.com']);

        $this->pushRequestForDomain('http://localhost:8060');

        $checker = static::getContainer()->get(UserChecker::class);
        $checker->checkPreAuth($superAdmin);

        $this->addToAssertionCount(1);
    }
}
