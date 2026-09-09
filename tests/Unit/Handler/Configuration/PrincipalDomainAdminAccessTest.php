<?php

namespace App\Tests\Unit\Handler\Configuration;

use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Entity\Users\UsersDomains;
use App\Handler\Configuration\PrincipalDomainAdminAccess;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PrincipalDomainAdminAccessTest extends TestCase
{
    private PrincipalDomainAdminAccess $service;

    protected function setUp(): void
    {
        $this->service = new PrincipalDomainAdminAccess();
    }

    private function mockTenant(bool $isPrincipal): Tenants&MockObject
    {
        $tenant = $this->createMock(Tenants::class);
        $tenant->method('isPrincipal')->willReturn($isPrincipal);
        return $tenant;
    }

    private function mockUser(bool $hasActiveAdminMembership): Users&MockObject
    {
        $usersDomains = [];
        if ($hasActiveAdminMembership) {
            $membership = $this->createMock(UsersDomains::class);
            $membership->method('getRoles')->willReturn(['ROLE_ADMIN']);
            $usersDomains[] = $membership;
        }

        $user = $this->createMock(Users::class);
        $user->method('getUserDomainsActives')->willReturn(new ArrayCollection($usersDomains));

        return $user;
    }

    public function testGrantsWhenTenantIsPrincipalAndUserHasAdminElsewhere(): void
    {
        $result = $this->service->isGranted($this->mockUser(true), $this->mockTenant(true));

        $this->assertTrue($result);
    }

    public function testDeniesWhenTenantIsNotPrincipal(): void
    {
        $result = $this->service->isGranted($this->mockUser(true), $this->mockTenant(false));

        $this->assertFalse($result);
    }

    public function testDeniesWhenUserHasNoActiveAdminMembershipAnywhere(): void
    {
        $result = $this->service->isGranted($this->mockUser(false), $this->mockTenant(true));

        $this->assertFalse($result);
    }

    public function testDeniesWhenMembershipExistsButIsNotRoleAdmin(): void
    {
        $membership = $this->createMock(UsersDomains::class);
        $membership->method('getRoles')->willReturn(['ROLE_USER']);
        $user = $this->createMock(Users::class);
        $user->method('getUserDomainsActives')->willReturn(new ArrayCollection([$membership]));

        $result = $this->service->isGranted($user, $this->mockTenant(true));

        $this->assertFalse($result);
    }
}
