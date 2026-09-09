<?php

namespace App\Tests\Unit\Entity\Tenants\Domains;

use App\ArgumentHandler\DomainsArgument;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use PHPUnit\Framework\TestCase;

class DomainsTest extends TestCase
{
    private function buildArgument(array $overrides = []): DomainsArgument
    {
        return new DomainsArgument(array_merge([
            'domain'            => 'https://tienda-de-ropa-elegante.miempresa.com',
            'name'              => 'Tienda Elegante',
            'logo'              => 'https://cdn.example.com/logo.png',
            'notificationEmail' => 'noti@example.com',
            'supportEmail'      => 'soporte@example.com',
        ], $overrides), new Tenants());
    }

    public function testAddSetsName(): void
    {
        $domain = new Domains();
        $domain->add($this->buildArgument(['name' => 'Mi Tienda']));

        $this->assertSame('Mi Tienda', $domain->getName());
    }

    public function testAddWithoutNameLeavesItNull(): void
    {
        $domain = new Domains();
        $data = ['domain' => 'https://largo.example.com', 'logo' => 'x', 'notificationEmail' => 'a@a.com', 'supportEmail' => 'a@a.com'];
        $domain->add(new DomainsArgument($data, new Tenants()));

        $this->assertNull($domain->getName());
    }

    public function testEditUpdatesName(): void
    {
        $domain = new Domains();
        $domain->add($this->buildArgument(['name' => 'Nombre Original']));
        $domain->edit($this->buildArgument(['name' => 'Nombre Actualizado']));

        $this->assertSame('Nombre Actualizado', $domain->getName());
    }

    public function testEditCanClearName(): void
    {
        $domain = new Domains();
        $domain->add($this->buildArgument(['name' => 'Con Nombre']));
        $data = ['domain' => 'https://largo.example.com', 'logo' => 'x', 'notificationEmail' => 'a@a.com', 'supportEmail' => 'a@a.com'];
        $domain->edit(new DomainsArgument($data, new Tenants()));

        $this->assertNull($domain->getName());
    }

    public function testGetDisplayNameReturnsNameWhenSet(): void
    {
        $domain = new Domains();
        $domain->add($this->buildArgument(['name' => 'Tienda Elegante', 'domain' => 'https://tienda-de-ropa-elegante-y-muy-larga.miempresa.com']));

        $this->assertSame('Tienda Elegante', $domain->getDisplayName());
    }

    public function testGetDisplayNameFallsBackToDomainWhenNameIsNull(): void
    {
        $domain = new Domains();
        $data = ['domain' => 'https://largo.example.com', 'logo' => 'x', 'notificationEmail' => 'a@a.com', 'supportEmail' => 'a@a.com'];
        $domain->add(new DomainsArgument($data, new Tenants()));

        $this->assertSame($domain->getDomain(), $domain->getDisplayName());
    }

    public function testAddSetsSocialLinksWhenProvided(): void
    {
        $domain = new Domains();
        $domain->add($this->buildArgument([
            'facebookUrl'  => 'https://facebook.com/mi-tienda',
            'instagramUrl' => 'https://instagram.com/mi-tienda',
            'pinterestUrl' => 'https://pinterest.com/mi-tienda',
        ]));

        $this->assertSame('https://facebook.com/mi-tienda', $domain->getFacebookUrl());
        $this->assertSame('https://instagram.com/mi-tienda', $domain->getInstagramUrl());
        $this->assertSame('https://pinterest.com/mi-tienda', $domain->getPinterestUrl());
    }

    public function testAddWithoutSocialLinksLeavesThemNull(): void
    {
        $domain = new Domains();
        $domain->add($this->buildArgument());

        $this->assertNull($domain->getFacebookUrl());
        $this->assertNull($domain->getInstagramUrl());
        $this->assertNull($domain->getPinterestUrl());
    }

    public function testEditUpdatesSocialLinks(): void
    {
        $domain = new Domains();
        $domain->add($this->buildArgument());
        $domain->edit($this->buildArgument(['facebookUrl' => 'https://facebook.com/nueva']));

        $this->assertSame('https://facebook.com/nueva', $domain->getFacebookUrl());
    }
}
