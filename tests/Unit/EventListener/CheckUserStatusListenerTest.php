<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\EventListener\CheckUserStatusListener;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\PrincipalDomainAdminAccessInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Uid\Uuid;

class CheckUserStatusListenerTest extends TestCase
{
    private GetDomainDataInterface&MockObject $getDomainData;
    private EntityManagerInterface&MockObject $entityManager;
    private PrincipalDomainAdminAccessInterface&MockObject $principalDomainAdminAccess;
    private Domains&MockObject $currentDomain;
    private CheckUserStatusListener $listener;

    protected function setUp(): void
    {
        $this->currentDomain = $this->createMock(Domains::class);
        $this->currentDomain->method('getId')->willReturn(Uuid::fromString('00000000-0000-0000-0000-000000000001'));

        $this->getDomainData = $this->createMock(GetDomainDataInterface::class);
        $this->getDomainData->method('getDomainCache')->willReturn($this->currentDomain);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->principalDomainAdminAccess = $this->createMock(PrincipalDomainAdminAccessInterface::class);

        $this->listener = new CheckUserStatusListener($this->getDomainData, $this->entityManager, $this->principalDomainAdminAccess);
    }

    private function mockUser(array $roles, bool $active = true, bool $validatedEmail = true, array $domainIds = []): Users&MockObject
    {
        $user = $this->createMock(Users::class);
        $user->method('isActive')->willReturn($active);
        $user->method('isValidatedEmail')->willReturn($validatedEmail);
        $user->method('getRoles')->willReturn($roles);

        $domains = new ArrayCollection(array_map(function (string $id) {
            $domain = $this->createMock(Domains::class);
            $domain->method('getId')->willReturn(Uuid::fromString($id));
            return $domain;
        }, $domainIds));
        $user->method('getDomainsActives')->willReturn($domains);

        return $user;
    }

    private function makeEvent(Users $user): InteractiveLoginEvent
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        return new InteractiveLoginEvent(new Request(), $token);
    }

    public function testSuperAdminWithoutUsersDomainsForCurrentDomainIsAllowed(): void
    {
        $user = $this->mockUser(['ROLE_SUPER_ADMIN'], domainIds: []);

        $this->listener->onInteractiveLogin($this->makeEvent($user));

        $this->addToAssertionCount(1);
    }

    public function testRegularAdminWithoutUsersDomainsForCurrentDomainIsBlocked(): void
    {
        $user = $this->mockUser(['ROLE_ADMIN'], domainIds: []);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('usuario no asociado a dominio');

        $this->listener->onInteractiveLogin($this->makeEvent($user));
    }

    public function testRegularUserWithoutUsersDomainsForCurrentDomainIsBlocked(): void
    {
        $user = $this->mockUser(['ROLE_USER'], domainIds: []);

        $this->expectException(CustomUserMessageAccountStatusException::class);

        $this->listener->onInteractiveLogin($this->makeEvent($user));
    }

    public function testRegularAdminWithUsersDomainsForCurrentDomainIsAllowed(): void
    {
        $user = $this->mockUser(['ROLE_ADMIN'], domainIds: ['00000000-0000-0000-0000-000000000001']);

        $this->listener->onInteractiveLogin($this->makeEvent($user));

        $this->addToAssertionCount(1);
    }

    public function testAllowedWhenPrincipalDomainAdminAccessGrants(): void
    {
        $this->principalDomainAdminAccess->method('isGranted')->willReturn(true);

        $user = $this->mockUser(['ROLE_ADMIN'], domainIds: []);

        $this->listener->onInteractiveLogin($this->makeEvent($user));

        $this->addToAssertionCount(1);
    }

    public function testInactiveUserIsBlockedRegardlessOfRole(): void
    {
        $user = $this->mockUser(['ROLE_SUPER_ADMIN'], active: false);

        $this->expectException(DisabledException::class);

        $this->listener->onInteractiveLogin($this->makeEvent($user));
    }

    public function testUnvalidatedEmailIsBlockedRegardlessOfRole(): void
    {
        $user = $this->mockUser(['ROLE_SUPER_ADMIN'], validatedEmail: false);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Debe validar su perfil.');

        $this->listener->onInteractiveLogin($this->makeEvent($user));
    }

    public function testGetSubscribedEventsReturnsInteractiveLogin(): void
    {
        $events = CheckUserStatusListener::getSubscribedEvents();
        $this->assertArrayHasKey('security.interactive_login', $events);
    }
}
