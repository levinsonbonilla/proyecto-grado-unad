<?php

namespace App\Tests\Integration\Handler\UseCase\Security;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Entity\Users\UsersDomains;
use App\Handler\UseCase\Security\RegisterTenantUseCase;
use App\Interface\UseCase\Security\RegisterTenantInterface;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\HttpFoundation\Request;

class RegisterTenantUseCaseTest extends IntegrationTestCase
{
    private function callHandler(array $data)
    {
        $requestStack = static::getContainer()->get('request_stack');

        $request = Request::create('http://localhost:8060/es/comenzar', 'POST');
        $request->setLocale('es');
        $requestStack->push($request);

        return static::getContainer()->get(RegisterTenantInterface::class)->handler($data);
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'businessName' => 'Negocio Integración',
            'slug' => 'negocio-integracion-' . substr(uniqid(), -6),
            'email' => 'integracion-' . uniqid() . '@example.com',
            'name' => 'Dueno',
            'lastName' => 'Integracion',
            'password' => 'SuperClave123',
        ], $overrides);
    }

    public function testServiceIsRegisteredInContainer(): void
    {
        $service = static::getContainer()->get(RegisterTenantInterface::class);

        $this->assertInstanceOf(RegisterTenantUseCase::class, $service);
    }

    public function testHandlerCreatesTenantDomainUserAndUsersDomainsWithRoleAdmin(): void
    {
        $data = $this->validData();

        $result = $this->callHandler($data);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());

        $tenant = $this->em->getRepository(Tenants::class)->findOneBy(['name' => $data['businessName']]);
        $this->assertNotNull($tenant);

        $this->assertFalse($tenant->isPrincipal());

        $domain = $this->em->getRepository(Domains::class)->findOneBy(['tenant' => $tenant]);
        $this->assertNotNull($domain);
        $this->assertStringContainsString($data['slug'], $domain->getDomain());

        $user = $this->em->getRepository(Users::class)->findOneBy(['email' => $data['email']]);
        $this->assertNotNull($user);
        $this->assertFalse($user->isValidatedEmail(), 'el login debe quedar bloqueado hasta confirmar el email');

        $usersDomains = $this->em->getRepository(UsersDomains::class)->findOneBy(['user' => $user, 'domain' => $domain]);
        $this->assertNotNull($usersDomains);
        $this->assertSame(['ROLE_ADMIN'], $usersDomains->getRoles());
    }

    public function testSameEmailAcrossTwoTenantsReusesUserWithSeparateRolesPerDomain(): void
    {
        $email = 'dueno-multi-' . uniqid() . '@example.com';

        $firstData = $this->validData(['email' => $email, 'slug' => 'primer-tenant-' . substr(uniqid(), -6)]);
        $this->callHandler($firstData);

        $secondData = $this->validData(['email' => $email, 'slug' => 'segundo-tenant-' . substr(uniqid(), -6)]);
        $result = $this->callHandler($secondData);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());

        $users = $this->em->getRepository(Users::class)->findBy(['email' => $email]);
        $this->assertCount(1, $users, 'el mismo email no debe crear un segundo Users');

        $usersDomains = $this->em->getRepository(UsersDomains::class)->findBy(['user' => $users[0]]);
        $this->assertCount(2, $usersDomains, 'debe haber una fila UsersDomains por tenant, ambas ROLE_ADMIN');
        foreach ($usersDomains as $ud) {
            $this->assertSame(['ROLE_ADMIN'], $ud->getRoles());
        }
    }

    public function testHandlerRejectsDuplicateSlug(): void
    {
        $data = $this->validData();
        $this->callHandler($data);

        $result = $this->callHandler($this->validData(['slug' => $data['slug']]));

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }

    public function testHandlerRejectsReservedSlug(): void
    {
        $result = $this->callHandler($this->validData(['slug' => 'dashboard']));

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }
}
