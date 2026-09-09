<?php

namespace App\Tests\Unit\Handler\Configuration;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Entity\Users\UsersDomains;
use App\Exception\GenericException;
use App\Handler\Configuration\ActiveDashboardDomainResolver;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\Repository\Tenants\TenantsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Uid\Uuid;

class ActiveDashboardDomainResolverTest extends TestCase
{
    private Security&MockObject $security;
    private DomainsRepository&MockObject $domainsRepository;
    private TenantsRepository&MockObject $tenantsRepository;
    private RequestStack&MockObject $requestStack;
    private Request&MockObject $request;
    private SessionInterface&MockObject $session;
    private ActiveDashboardDomainResolver $resolver;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->domainsRepository = $this->createMock(DomainsRepository::class);
        $this->tenantsRepository = $this->createMock(TenantsRepository::class);

        $this->session = $this->createMock(SessionInterface::class);
        $this->request = $this->createMock(Request::class);
        $this->request->method('getSession')->willReturn($this->session);

        $this->requestStack = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($this->request);

        $this->resolver = new ActiveDashboardDomainResolver(
            $this->security,
            $this->domainsRepository,
            $this->tenantsRepository,
            $this->requestStack,
        );
    }

    private function domainWithId(string $id): Domains&MockObject
    {
        $domain = $this->createMock(Domains::class);
        $domain->method('getId')->willReturn(Uuid::fromString($id));
        return $domain;
    }

    private function userWithMemberships(array $memberships): Users&MockObject
    {
        $user = $this->createMock(Users::class);
        $user->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);
        $user->method('getUserDomainsActives')->willReturn(new ArrayCollection($memberships));
        return $user;
    }

    private function membership(Domains $domain, array $roles): UsersDomains&MockObject
    {
        $ud = $this->createMock(UsersDomains::class);
        $ud->method('getDomain')->willReturn($domain);
        $ud->method('getRoles')->willReturn($roles);
        return $ud;
    }

    private function mockCurrentHostTenant(Tenants $tenant): void
    {
        $hostDomain = $this->createMock(Domains::class);
        $tenantIdOnlyProxy = $this->createMock(Tenants::class);
        $tenantIdOnlyProxy->method('getId')->willReturn(Uuid::fromString('99999999-0000-0000-0000-000000000000'));
        $hostDomain->method('getTenant')->willReturn($tenantIdOnlyProxy);
        $this->domainsRepository->method('getDomainCache')->willReturn($hostDomain);
        $this->tenantsRepository->method('getTenantCache')->willReturn($tenant);
    }

    public function testResolveReturnsNullWhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $this->assertNull($this->resolver->resolve($this->request));
    }

    public function testResolveReturnsDomainForSuperAdminWhenSessionMatchesCurrentTenantDomain(): void
    {
        $domainA = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $tenant = $this->createMock(Tenants::class);
        $this->mockCurrentHostTenant($tenant);

        $superAdmin = $this->createMock(Users::class);
        $superAdmin->method('getRoles')->willReturn(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER']);
        $superAdmin->method('getUserDomainsActives')->willReturn(new ArrayCollection([]));
        $this->security->method('getUser')->willReturn($superAdmin);
        $this->session->method('get')->willReturn('11111111-1111-1111-1111-111111111111');
        $this->domainsRepository->method('find')->willReturn($domainA);
        $this->domainsRepository->method('getActiveDomainsByTenant')->willReturn([$domainA]);

        $result = $this->resolver->resolve($this->request);

        $this->assertSame($domainA, $result);
    }

    public function testResolveClearsSessionForSuperAdminWhenDomainBelongsToDifferentTenant(): void
    {
        $domainOtherTenant = $this->domainWithId('33333333-3333-3333-3333-333333333333');
        $domainCurrentTenant = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $tenant = $this->createMock(Tenants::class);
        $this->mockCurrentHostTenant($tenant);

        $superAdmin = $this->createMock(Users::class);
        $superAdmin->method('getRoles')->willReturn(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER']);
        $superAdmin->method('getUserDomainsActives')->willReturn(new ArrayCollection([]));
        $this->security->method('getUser')->willReturn($superAdmin);
        $this->session->method('get')->willReturn('33333333-3333-3333-3333-333333333333');
        $this->domainsRepository->method('find')->willReturn($domainOtherTenant);
        $this->domainsRepository->method('getActiveDomainsByTenant')->willReturn([$domainCurrentTenant]);

        $this->session->expects($this->once())->method('remove')->with(ActiveDashboardDomainResolver::SESSION_KEY);

        $this->assertNull($this->resolver->resolve($this->request));
    }

    public function testResolveReturnsNullWhenNothingSelectedYet(): void
    {
        $domainA = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $user = $this->userWithMemberships([$this->membership($domainA, ['ROLE_ADMIN'])]);
        $this->security->method('getUser')->willReturn($user);
        $this->session->method('get')->with(ActiveDashboardDomainResolver::SESSION_KEY)->willReturn(null);

        $this->assertNull($this->resolver->resolve($this->request));
    }

    public function testResolveReturnsDomainWhenSessionMatchesSelectable(): void
    {
        $domainA = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $user = $this->userWithMemberships([$this->membership($domainA, ['ROLE_ADMIN'])]);
        $this->security->method('getUser')->willReturn($user);
        $this->session->method('get')->willReturn('11111111-1111-1111-1111-111111111111');
        $this->domainsRepository->method('find')->willReturn($domainA);

        $result = $this->resolver->resolve($this->request);

        $this->assertSame($domainA, $result);
    }

    public function testResolveClearsSessionAndReturnsNullWhenDomainNoLongerExists(): void
    {
        $user = $this->userWithMemberships([]);
        $this->security->method('getUser')->willReturn($user);
        $this->session->method('get')->willReturn('11111111-1111-1111-1111-111111111111');
        $this->domainsRepository->method('find')->willReturn(null);

        $this->session->expects($this->once())->method('remove')->with(ActiveDashboardDomainResolver::SESSION_KEY);

        $this->assertNull($this->resolver->resolve($this->request));
    }

    public function testResolveClearsSessionAndReturnsNullWhenUserLostAccessToDomain(): void
    {
        $domainA = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $domainB = $this->domainWithId('22222222-2222-2222-2222-222222222222');

        $user = $this->userWithMemberships([$this->membership($domainA, ['ROLE_ADMIN'])]);
        $this->security->method('getUser')->willReturn($user);
        $this->session->method('get')->willReturn('22222222-2222-2222-2222-222222222222');
        $this->domainsRepository->method('find')->willReturn($domainB);

        $this->session->expects($this->once())->method('remove')->with(ActiveDashboardDomainResolver::SESSION_KEY);

        $this->assertNull($this->resolver->resolve($this->request));
    }

    public function testGetSelectableDomainsReturnsEmptyArrayWhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $this->assertSame([], $this->resolver->getSelectableDomains());
    }

    public function testGetSelectableDomainsOnlyIncludesRoleAdminMemberships(): void
    {
        $adminDomain = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $userOnlyDomain = $this->domainWithId('22222222-2222-2222-2222-222222222222');
        $user = $this->userWithMemberships([
            $this->membership($adminDomain, ['ROLE_ADMIN']),
            $this->membership($userOnlyDomain, ['ROLE_USER']),
        ]);
        $this->security->method('getUser')->willReturn($user);

        $result = $this->resolver->getSelectableDomains();

        $this->assertCount(1, $result);
        $this->assertSame($adminDomain, $result[0]);
    }

    public function testGetSelectableDomainsReturnsOnlyCurrentHostTenantDomainsForSuperAdmin(): void
    {
        $domainA = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $domainB = $this->domainWithId('22222222-2222-2222-2222-222222222222');
        $tenant = $this->createMock(Tenants::class);
        $this->mockCurrentHostTenant($tenant);

        $superAdmin = $this->createMock(Users::class);
        $superAdmin->method('getRoles')->willReturn(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER']);
        $this->security->method('getUser')->willReturn($superAdmin);
        $this->domainsRepository->method('getActiveDomainsByTenant')
            ->with($tenant)
            ->willReturn([$domainA, $domainB]);

        $result = $this->resolver->getSelectableDomains();

        $this->assertSame([$domainA, $domainB], $result);
    }

    public function testGetSelectableDomainsReturnsEmptyArrayForSuperAdminWhenHostDoesNotResolve(): void
    {
        $this->domainsRepository->method('getDomainCache')->willReturn(null);

        $superAdmin = $this->createMock(Users::class);
        $superAdmin->method('getRoles')->willReturn(['ROLE_SUPER_ADMIN']);
        $this->security->method('getUser')->willReturn($superAdmin);

        $this->assertSame([], $this->resolver->getSelectableDomains());
    }

    public function testAssertUsableDoesNotThrowForSelectableDomain(): void
    {
        $domainA = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $user = $this->userWithMemberships([$this->membership($domainA, ['ROLE_ADMIN'])]);
        $this->security->method('getUser')->willReturn($user);

        $this->resolver->assertUsable($domainA);
        $this->addToAssertionCount(1);
    }

    public function testAssertUsableThrowsForDomainNotBelongingToUser(): void
    {
        $domainA = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $foreignDomain = $this->domainWithId('99999999-9999-9999-9999-999999999999');
        $user = $this->userWithMemberships([$this->membership($domainA, ['ROLE_ADMIN'])]);
        $this->security->method('getUser')->willReturn($user);

        $this->expectException(GenericException::class);
        $this->resolver->assertUsable($foreignDomain);
    }

    public function testAssertUsableDoesNotThrowForSuperAdminOnCurrentTenantDomain(): void
    {
        $domainA = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $tenant = $this->createMock(Tenants::class);
        $this->mockCurrentHostTenant($tenant);

        $superAdmin = $this->createMock(Users::class);
        $superAdmin->method('getRoles')->willReturn(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER']);
        $this->security->method('getUser')->willReturn($superAdmin);
        $this->domainsRepository->method('getActiveDomainsByTenant')->willReturn([$domainA]);

        $this->resolver->assertUsable($domainA);
        $this->addToAssertionCount(1);
    }

    public function testAssertUsableThrowsForSuperAdminOnDomainOfDifferentTenant(): void
    {
        $domainCurrentTenant = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $domainOtherTenant = $this->domainWithId('44444444-4444-4444-4444-444444444444');
        $tenant = $this->createMock(Tenants::class);
        $this->mockCurrentHostTenant($tenant);

        $superAdmin = $this->createMock(Users::class);
        $superAdmin->method('getRoles')->willReturn(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER']);
        $this->security->method('getUser')->willReturn($superAdmin);
        $this->domainsRepository->method('getActiveDomainsByTenant')->willReturn([$domainCurrentTenant]);

        $this->expectException(GenericException::class);
        $this->resolver->assertUsable($domainOtherTenant);
    }

    public function testAssertUsableThrowsWhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $this->expectException(GenericException::class);
        $this->resolver->assertUsable($this->domainWithId('11111111-1111-1111-1111-111111111111'));
    }
}
