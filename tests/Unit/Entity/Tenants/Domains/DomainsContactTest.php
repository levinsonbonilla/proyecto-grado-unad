<?php

namespace App\Tests\Unit\Entity\Tenants\Domains;

use App\Entity\Tenants\Domains\Domains;
use PHPUnit\Framework\TestCase;

class DomainsContactTest extends TestCase
{
    public function testContactFieldsAreNullByDefault(): void
    {
        $domain = new Domains();

        $this->assertNull($domain->getContactAddress());
        $this->assertNull($domain->getContactPhone());
        $this->assertNull($domain->getContactEmail());
    }

    public function testChangeContactStoresValues(): void
    {
        $domain = new Domains();
        $domain->changeContact('Calle 1 # 2-3', '+57 300 000 0000', 'ventas@example.com');

        $this->assertSame('Calle 1 # 2-3', $domain->getContactAddress());
        $this->assertSame('+57 300 000 0000', $domain->getContactPhone());
        $this->assertSame('ventas@example.com', $domain->getContactEmail());
    }

    public function testChangeContactTrimsValues(): void
    {
        $domain = new Domains();
        $domain->changeContact('  Calle 1  ', ' 123 ', '  a@b.com ');

        $this->assertSame('Calle 1', $domain->getContactAddress());
        $this->assertSame('123', $domain->getContactPhone());
        $this->assertSame('a@b.com', $domain->getContactEmail());
    }

    public function testChangeContactConvertsBlankValuesToNull(): void
    {
        $domain = new Domains();
        $domain->changeContact('Calle 1', '123', 'a@b.com');
        $domain->changeContact('   ', '', null);

        $this->assertNull($domain->getContactAddress());
        $this->assertNull($domain->getContactPhone());
        $this->assertNull($domain->getContactEmail());
    }

    public function testChangeContactCanUpdateOnlySomeFields(): void
    {
        $domain = new Domains();
        $domain->changeContact('Calle 1', '123', 'a@b.com');
        $domain->changeContact('Calle 1', null, 'a@b.com');

        $this->assertSame('Calle 1', $domain->getContactAddress());
        $this->assertNull($domain->getContactPhone());
        $this->assertSame('a@b.com', $domain->getContactEmail());
    }
}
