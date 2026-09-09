<?php

namespace App\Tests\Integration\Handler\UseCase\Dashboard\Tenants;

use App\Handler\UseCase\Dashboard\Tenants\AddTenantUseCase;
use App\Interface\UseCase\Dashboard\Tenants\AddTenantInterface;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\HttpFoundation\Request;

class AddTenantUseCaseTest extends IntegrationTestCase
{
    public function testServiceIsRegisteredInContainer(): void
    {
        $service = static::getContainer()->get(AddTenantInterface::class);

        $this->assertInstanceOf(AddTenantUseCase::class, $service);
    }

    public function testServiceImplementsCorrectInterface(): void
    {
        $service = static::getContainer()->get(AddTenantInterface::class);

        $this->assertInstanceOf(AddTenantInterface::class, $service);
    }

    public function testHandlerReturnsFormReturnWithoutRequest(): void
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(new Request());

        $service = static::getContainer()->get(AddTenantInterface::class);
        $result  = $service->handler();

        $this->assertFalse($result->isProcess());
        $this->assertFalse($result->isError());
        $this->assertNull($result->getMessage());
        $this->assertNotNull($result->getForm());
    }

    public function testFixtureTenantExistsInDatabase(): void
    {
        $repo   = $this->em->getRepository(\App\Entity\Tenants\Tenants::class);
        $tenant = $repo->findOneBy(['nit' => '0']);

        $this->assertNotNull($tenant, 'Fixture tenant with nit=0 must exist in the database');
        $this->assertSame('proyecto-grado-unad', $tenant->getName());
    }

    public function testHandlerRejectsDuplicateNitFromFixture(): void
    {

        $requestStack = static::getContainer()->get('request_stack');
        $request      = Request::create('/tenants/new', 'POST', [
            'tenants' => [
                'name'        => 'Duplicado',
                'description' => 'Descripción',
                'nit'         => '0',
                'phone'       => '3001234567',
                'prefix'      => '+57',
            ],
        ]);
        $requestStack->push($request);

        $service = static::getContainer()->get(AddTenantInterface::class);
        $result  = $service->handler();

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
        $this->assertNotNull($result->getMessage());
    }
}
