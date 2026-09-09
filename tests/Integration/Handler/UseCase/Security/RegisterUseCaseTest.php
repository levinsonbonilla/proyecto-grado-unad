<?php

namespace App\Tests\Integration\Handler\UseCase\Security;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Entity\Users\UsersDomains;
use App\Interface\UseCase\Security\RegisterInterface;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\HttpFoundation\Request;

class RegisterUseCaseTest extends IntegrationTestCase
{
    private function callHandler(string $host, array $data)
    {
        $requestStack = static::getContainer()->get('request_stack');
        $request = Request::create($host . '/es/register', 'POST');
        $request->setLocale('es');
        $requestStack->push($request);

        return static::getContainer()->get(RegisterInterface::class)->handler($data);
    }

    public function testRegisterOnPrincipalDomainCreatesTenantDomainAndAdminUser(): void
    {
        $email = 'dueno-' . uniqid() . '@example.com';

        $result = $this->callHandler('http://localhost:8060', [
            'businessName' => 'Negocio Integración Register',
            'phone' => '3005551234',
            'email' => $email,
            'name' => 'Dueño',
            'lastName' => 'Prueba',
            'password' => 'SuperClave123',
        ]);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError(), (string) $result->getMessage());

        $tenant = $this->em->getRepository(Tenants::class)->findOneBy(['name' => 'Negocio Integración Register']);
        $this->assertNotNull($tenant);
        $this->assertFalse($tenant->isPrincipal(), 'el autoservicio nunca puede crear un tenant plataforma');
        $this->assertSame('0', $tenant->getNit());
        $this->assertSame('3005551234', $tenant->getPhone());

        $domain = $this->em->getRepository(Domains::class)->findOneBy(['tenant' => $tenant]);
        $this->assertNotNull($domain);
        $this->assertSame($domain->getNotificationEmail(), $email);
        $this->assertSame($domain->getSupportEmail(), $email);

        $user = $this->em->getRepository(Users::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($user);
        $this->assertFalse($user->isValidatedEmail(), 'el login debe quedar bloqueado hasta confirmar el email');

        $usersDomains = $this->em->getRepository(UsersDomains::class)->findOneBy(['user' => $user, 'domain' => $domain]);
        $this->assertNotNull($usersDomains);
        $this->assertSame(['ROLE_ADMIN'], $usersDomains->getRoles());
    }

    public function testRegisterOnNonPrincipalDomainStillCreatesCustomerWithRoleUser(): void
    {
        $email = 'cliente-' . uniqid() . '@example.com';

        $result = $this->callHandler('http://localhost:8090', [
            'email' => $email,
            'name' => 'Cliente',
            'lastName' => 'Prueba',
            'password' => 'SuperClave123',
        ]);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());

        $this->assertNull($this->em->getRepository(Tenants::class)->findOneBy(['name' => $email]));

        $user = $this->em->getRepository(Users::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($user);

        $domain = $this->em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8090']);
        $this->assertNotNull($domain);

        $usersDomains = $this->em->getRepository(UsersDomains::class)->findOneBy(['user' => $user, 'domain' => $domain]);
        $this->assertNotNull($usersDomains);
        $this->assertSame(['ROLE_USER'], $usersDomains->getRoles());
    }
}
