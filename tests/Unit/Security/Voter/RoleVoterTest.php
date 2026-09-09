<?php

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Entity\Users\UsersDomains;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\PrincipalDomainAdminAccessInterface;
use App\Security\Voter\RoleVoter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class RoleVoterTest extends TestCase
{
    private GetDomainDataInterface&MockObject $getDomainData;
    private PrincipalDomainAdminAccessInterface&MockObject $principalDomainAdminAccess;
    private Domains&MockObject $currentDomain;
    private RoleVoter $voter;

    protected function setUp(): void
    {
        $this->currentDomain = $this->createMock(Domains::class);

        $this->getDomainData = $this->createMock(GetDomainDataInterface::class);
        $this->getDomainData->method('getDomainCache')->willReturn($this->currentDomain);

        $this->principalDomainAdminAccess = $this->createMock(PrincipalDomainAdminAccessInterface::class);

        $this->voter = new RoleVoter($this->getDomainData, $this->principalDomainAdminAccess);
    }

    private function tokenFor(?Users $user): TokenInterface&MockObject
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        return $token;
    }

    public function testGrantsWhenUsersDomainsForCurrentDomainHasTheRole(): void
    {
        $membership = $this->createMock(UsersDomains::class);
        $membership->method('getRoles')->willReturn(['ROLE_ADMIN']);

        $user = $this->createMock(Users::class);
        $user->method('getUsersDomainsActivesByDomain')->willReturn($membership);

        $result = $this->voter->vote($this->tokenFor($user), null, ['ROLE_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeniesWhenUsersDomainsForCurrentDomainLacksTheRole(): void
    {
        $membership = $this->createMock(UsersDomains::class);
        $membership->method('getRoles')->willReturn(['ROLE_USER']);

        $user = $this->createMock(Users::class);
        $user->method('getUsersDomainsActivesByDomain')->willReturn($membership);

        $result = $this->voter->vote($this->tokenFor($user), null, ['ROLE_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeniesWhenNoMembershipAndNoPrincipalDomainAccess(): void
    {
        $user = $this->createMock(Users::class);
        $user->method('getUsersDomainsActivesByDomain')->willReturn(false);

        $result = $this->voter->vote($this->tokenFor($user), null, ['ROLE_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testGrantsRoleAdminWhenPrincipalDomainAdminAccessGrants(): void
    {
        $this->principalDomainAdminAccess->method('isGranted')->willReturn(true);

        $user = $this->createMock(Users::class);
        $user->method('getUsersDomainsActivesByDomain')->willReturn(false);

        $result = $this->voter->vote($this->tokenFor($user), null, ['ROLE_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDoesNotGrantRoleUserEvenWhenPrincipalDomainAdminAccessGrants(): void
    {
        $this->principalDomainAdminAccess->method('isGranted')->willReturn(true);

        $user = $this->createMock(Users::class);
        $user->method('getUsersDomainsActivesByDomain')->willReturn(false);

        $result = $this->voter->vote($this->tokenFor($user), null, ['ROLE_USER']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testAbstainsForUnsupportedAttribute(): void
    {
        $user = $this->createMock(Users::class);

        $result = $this->voter->vote($this->tokenFor($user), null, ['SOME_OTHER_ATTRIBUTE']);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }
}
