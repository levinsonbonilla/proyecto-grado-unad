<?php

namespace App\Tests\Unit\ArgumentHandler;

use App\ArgumentHandler\TenantsArgument;
use PHPUnit\Framework\TestCase;

class TenantsArgumentTest extends TestCase
{
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name'        => 'Empresa Test',
            'description' => 'Descripción de prueba',
            'nit'         => '900123456',
            'phone'       => '3001234567',
            'prefix'      => '+57',
        ], $overrides);
    }

    public function testConstructorWithAllFields(): void
    {
        $argument = new TenantsArgument($this->validData());

        $this->assertSame('Empresa Test', $argument->getName());
        $this->assertSame('Descripción de prueba', $argument->getDescription());
        $this->assertSame('900123456', $argument->getNit());
        $this->assertSame('3001234567', $argument->getPhone());
        $this->assertSame('+57', $argument->getPrefix());
    }

    public function testConstructorWithOnlyRequiredFields(): void
    {
        $argument = new TenantsArgument([
            'name'        => 'Solo requeridos',
            'description' => 'Descripción',
        ]);

        $this->assertSame('Solo requeridos', $argument->getName());
        $this->assertSame('Descripción', $argument->getDescription());
    }

    public function testConstructorThrowsWhenNameMissing(): void
    {
        $this->expectException(\Exception::class);

        new TenantsArgument(['description' => 'Sin nombre']);
    }

    public function testConstructorThrowsWhenDescriptionMissing(): void
    {
        $this->expectException(\Exception::class);

        new TenantsArgument(['name' => 'Sin descripción']);
    }

    public function testConstructorThrowsWhenDataIsEmpty(): void
    {
        $this->expectException(\Exception::class);

        new TenantsArgument([]);
    }

    public function testGetNameReturnsCorrectValue(): void
    {
        $argument = new TenantsArgument($this->validData(['name' => 'Mi Empresa']));

        $this->assertSame('Mi Empresa', $argument->getName());
    }

    public function testGetDescriptionReturnsCorrectValue(): void
    {
        $argument = new TenantsArgument($this->validData(['description' => 'Mi Descripción']));

        $this->assertSame('Mi Descripción', $argument->getDescription());
    }
}
