<?php

namespace App\Tests\Unit\Entity\Tenants;

use App\ArgumentHandler\TenantsArgument;
use App\Entity\Tenants\Tenants;
use PHPUnit\Framework\TestCase;

class TenantsTest extends TestCase
{
    private function buildArgument(array $overrides = []): TenantsArgument
    {
        return new TenantsArgument(array_merge([
            'name'        => 'Empresa Test',
            'description' => 'Descripción test',
            'nit'         => '900123456',
            'phone'       => '3001234567',
            'prefix'      => '+57',
        ], $overrides));
    }

    public function testAddActivatesEntity(): void
    {
        $tenant = new Tenants();
        $tenant->add($this->buildArgument());

        $this->assertTrue($tenant->isActive());
    }

    public function testAddSetsName(): void
    {
        $tenant = new Tenants();
        $tenant->add($this->buildArgument(['name' => 'Mi Empresa']));

        $this->assertSame('Mi Empresa', $tenant->getName());
    }

    public function testAddSetsDescription(): void
    {
        $tenant = new Tenants();
        $tenant->add($this->buildArgument(['description' => 'Mi Descripción']));

        $this->assertSame('Mi Descripción', $tenant->getDescription());
    }

    public function testAddSetsNit(): void
    {
        $tenant = new Tenants();
        $tenant->add($this->buildArgument(['nit' => '987654321']));

        $this->assertSame('987654321', $tenant->getNit());
    }

    public function testAddSetsPhone(): void
    {
        $tenant = new Tenants();
        $tenant->add($this->buildArgument(['phone' => '6011234567']));

        $this->assertSame('6011234567', $tenant->getPhone());
    }

    public function testAddSetsPrefix(): void
    {
        $tenant = new Tenants();
        $tenant->add($this->buildArgument(['prefix' => '+1']));

        $this->assertSame('+1', $tenant->getPrefix());
    }

    public function testEditUpdatesName(): void
    {
        $tenant = new Tenants();
        $tenant->add($this->buildArgument());
        $tenant->edit($this->buildArgument(['name' => 'Nombre Actualizado']));

        $this->assertSame('Nombre Actualizado', $tenant->getName());
    }

    public function testEditUpdatesDescription(): void
    {
        $tenant = new Tenants();
        $tenant->add($this->buildArgument());
        $tenant->edit($this->buildArgument(['description' => 'Nueva Descripción']));

        $this->assertSame('Nueva Descripción', $tenant->getDescription());
    }

    public function testEditUpdatesPhone(): void
    {
        $tenant = new Tenants();
        $tenant->add($this->buildArgument(['phone' => '3000000000']));
        $tenant->edit($this->buildArgument(['phone' => '3111111111']));

        $this->assertSame('3111111111', $tenant->getPhone());
    }

    public function testEditDoesNotChangeNit(): void
    {
        $tenant = new Tenants();
        $tenant->add($this->buildArgument(['nit' => '111111111']));
        $tenant->edit($this->buildArgument(['nit' => '999999999']));

        $this->assertSame('111111111', $tenant->getNit());
    }

    public function testDeactivate(): void
    {
        $tenant = new Tenants();
        $tenant->add($this->buildArgument());
        $tenant->deactivate();

        $this->assertFalse($tenant->isActive());
    }

    public function testActivateAfterDeactivate(): void
    {
        $tenant = new Tenants();
        $tenant->add($this->buildArgument());
        $tenant->deactivate();
        $tenant->activate();

        $this->assertTrue($tenant->isActive());
    }

    public function testIsPrincipalDefaultsToFalse(): void
    {
        $tenant = new Tenants();
        $tenant->add($this->buildArgument());

        $this->assertFalse($tenant->isPrincipal());
    }

    public function testMarkAsPrincipal(): void
    {
        $tenant = new Tenants();
        $tenant->add($this->buildArgument());
        $tenant->markAsPrincipal();

        $this->assertTrue($tenant->isPrincipal());
    }

    public function testUnmarkAsPrincipal(): void
    {
        $tenant = new Tenants();
        $tenant->add($this->buildArgument());
        $tenant->markAsPrincipal();
        $tenant->unmarkAsPrincipal();

        $this->assertFalse($tenant->isPrincipal());
    }

    public function testAddReturnsTenantsInstance(): void
    {
        $tenant = new Tenants();
        $result = $tenant->add($this->buildArgument());

        $this->assertInstanceOf(Tenants::class, $result);
    }

    public function testEditReturnsTenantsInstance(): void
    {
        $tenant = new Tenants();
        $tenant->add($this->buildArgument());
        $result = $tenant->edit($this->buildArgument());

        $this->assertInstanceOf(Tenants::class, $result);
    }
}
