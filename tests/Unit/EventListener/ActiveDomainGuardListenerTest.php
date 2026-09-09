<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\EventListener\ActiveDomainGuardListener;
use App\Handler\Configuration\ActiveDashboardDomainResolver;
use App\Interface\Configuration\ActiveDashboardDomainResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

class ActiveDomainGuardListenerTest extends TestCase
{
    private HttpKernelInterface&MockObject $kernel;
    private Security&MockObject $security;
    private ActiveDashboardDomainResolverInterface&MockObject $activeDomainResolver;
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private Environment&MockObject $twig;
    private ActiveDomainGuardListener $listener;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(HttpKernelInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->activeDomainResolver = $this->createMock(ActiveDashboardDomainResolverInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->listener = new ActiveDomainGuardListener(
            $this->security,
            $this->activeDomainResolver,
            $this->urlGenerator,
            $this->twig,
        );
    }

    private function buildEvent(string $pathAndRoute, bool $withSession = true, bool $isMainRequest = true): RequestEvent
    {
        $request = Request::create('http://localhost' . $pathAndRoute);
        $request->attributes->set('_route', ltrim(str_replace('/', '_', $pathAndRoute), '_'));
        if ($withSession) {
            $request->setSession(new Session(new MockArraySessionStorage()));
        }

        return new RequestEvent(
            $this->kernel,
            $request,
            $isMainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST
        );
    }

    private function domainWithId(string $id, string $host = 'http://example.test'): Domains&MockObject
    {
        $domain = $this->createMock(Domains::class);
        $domain->method('getId')->willReturn(Uuid::fromString($id));
        $domain->method('getDomain')->willReturn($host);
        return $domain;
    }

    private function setRoute(RequestEvent $event, string $route): void
    {
        $event->getRequest()->attributes->set('_route', $route);
    }

    public function testDoesNothingOutsideDashboardPath(): void
    {
        $this->security->expects($this->never())->method('getUser');
        $event = $this->buildEvent('/es/store/some-product');

        $this->listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testDoesNothingForSubRequests(): void
    {
        $this->security->expects($this->never())->method('getUser');
        $event = $this->buildEvent('/es/dashboard/products/manager', isMainRequest: false);

        $this->listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testDoesNothingForAnonymousUser(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $event = $this->buildEvent('/es/dashboard/products/manager');

        $this->listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testForcesSelectionScreenForSuperAdminWithMultipleDomainsAndNothingInSession(): void
    {
        $superAdmin = $this->createMock(Users::class);
        $superAdmin->method('getRoles')->willReturn(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER']);
        $this->security->method('getUser')->willReturn($superAdmin);
        $domainA = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $domainB = $this->domainWithId('22222222-2222-2222-2222-222222222222');
        $this->activeDomainResolver->method('getSelectableDomains')->willReturn([$domainA, $domainB]);
        $this->twig->method('render')->willReturn('<html>select a domain</html>');

        $event = $this->buildEvent('/es/dashboard/products/manager');
        $this->setRoute($event, 'dashboard_products_manager');

        $this->listener->onKernelRequest($event);

        $this->assertTrue($event->hasResponse());
        $this->assertStringContainsString('select a domain', $event->getResponse()->getContent());
    }

    public function testAutoSelectsSilentlyForSuperAdminWithExactlyOneDomain(): void
    {
        $superAdmin = $this->createMock(Users::class);
        $superAdmin->method('getRoles')->willReturn(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER']);
        $this->security->method('getUser')->willReturn($superAdmin);
        $onlyDomain = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $this->activeDomainResolver->method('getSelectableDomains')->willReturn([$onlyDomain]);

        $event = $this->buildEvent('/es/dashboard/products/manager');
        $this->setRoute($event, 'dashboard_products_manager');

        $this->listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
        $this->assertSame(
            '11111111-1111-1111-1111-111111111111',
            $event->getRequest()->getSession()->get(ActiveDashboardDomainResolver::SESSION_KEY)
        );
    }

    public function testSuperAdminBypassesGuardOnUsersRouteEvenWithMultipleDomains(): void
    {
        $superAdmin = $this->createMock(Users::class);
        $superAdmin->method('getRoles')->willReturn(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER']);
        $this->security->method('getUser')->willReturn($superAdmin);
        $this->activeDomainResolver->expects($this->never())->method('getSelectableDomains');

        $event = $this->buildEvent('/es/dashboard/users/new');
        $this->setRoute($event, 'dashboard_users_new');

        $this->listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testRegularAdminStillGoesThroughGuardOnUsersRoute(): void
    {
        $admin = $this->createMock(Users::class);
        $admin->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);
        $this->security->method('getUser')->willReturn($admin);
        $domainA = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $domainB = $this->domainWithId('22222222-2222-2222-2222-222222222222');
        $this->activeDomainResolver->method('getSelectableDomains')->willReturn([$domainA, $domainB]);
        $this->twig->method('render')->willReturn('<html>select a domain</html>');

        $event = $this->buildEvent('/es/dashboard/users/new');
        $this->setRoute($event, 'dashboard_users_new');

        $this->listener->onKernelRequest($event);

        $this->assertTrue($event->hasResponse());
    }

    public function testAllowsRouteInAllowlistEvenWithZeroDomains(): void
    {
        $admin = $this->createMock(Users::class);
        $admin->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);
        $this->security->method('getUser')->willReturn($admin);
        $this->activeDomainResolver->method('getSelectableDomains')->willReturn([]);

        $event = $this->buildEvent('/es/dashboard/configurations/domains/new');
        $this->setRoute($event, 'dashboard_configurations_domains_new');

        $this->listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testRedirectsToCreateDomainWhenUserHasZeroSelectableDomains(): void
    {
        $admin = $this->createMock(Users::class);
        $admin->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);
        $this->security->method('getUser')->willReturn($admin);
        $this->activeDomainResolver->method('getSelectableDomains')->willReturn([]);
        $this->urlGenerator->method('generate')
            ->with('dashboard_configurations_domains_new', $this->anything())
            ->willReturn('/es/dashboard/configurations/domains/new');

        $event = $this->buildEvent('/es/dashboard/products/manager');
        $this->setRoute($event, 'dashboard_products_manager');

        $this->listener->onKernelRequest($event);

        $this->assertTrue($event->hasResponse());
        $this->assertInstanceOf(RedirectResponse::class, $event->getResponse());
    }

    public function testAutoSelectsSilentlyWhenExactlyOneDomainAndNothingInSession(): void
    {
        $admin = $this->createMock(Users::class);
        $admin->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);
        $this->security->method('getUser')->willReturn($admin);
        $onlyDomain = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $this->activeDomainResolver->method('getSelectableDomains')->willReturn([$onlyDomain]);

        $event = $this->buildEvent('/es/dashboard/products/manager');
        $this->setRoute($event, 'dashboard_products_manager');

        $this->listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
        $this->assertSame(
            '11111111-1111-1111-1111-111111111111',
            $event->getRequest()->getSession()->get(ActiveDashboardDomainResolver::SESSION_KEY)
        );
    }

    public function testDoesNotOverwriteSessionWhenOneDomainAndAlreadySelected(): void
    {
        $admin = $this->createMock(Users::class);
        $admin->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);
        $this->security->method('getUser')->willReturn($admin);
        $onlyDomain = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $this->activeDomainResolver->method('getSelectableDomains')->willReturn([$onlyDomain]);

        $event = $this->buildEvent('/es/dashboard/products/manager');
        $this->setRoute($event, 'dashboard_products_manager');
        $event->getRequest()->getSession()->set(ActiveDashboardDomainResolver::SESSION_KEY, 'ya-habia-algo');

        $this->listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
        $this->assertSame(
            'ya-habia-algo',
            $event->getRequest()->getSession()->get(ActiveDashboardDomainResolver::SESSION_KEY)
        );
    }

    public function testForcesSelectionScreenWhenMultipleDomainsAndNothingInSession(): void
    {
        $admin = $this->createMock(Users::class);
        $admin->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);
        $this->security->method('getUser')->willReturn($admin);
        $domainA = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $domainB = $this->domainWithId('22222222-2222-2222-2222-222222222222');
        $this->activeDomainResolver->method('getSelectableDomains')->willReturn([$domainA, $domainB]);
        $this->twig->method('render')->willReturn('<html>select a domain</html>');

        $event = $this->buildEvent('/es/dashboard/products/manager');
        $this->setRoute($event, 'dashboard_products_manager');

        $this->listener->onKernelRequest($event);

        $this->assertTrue($event->hasResponse());
        $this->assertStringContainsString('select a domain', $event->getResponse()->getContent());
    }

    public function testDoesNotForceSelectionScreenWhenAlreadySelected(): void
    {
        $admin = $this->createMock(Users::class);
        $admin->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);
        $this->security->method('getUser')->willReturn($admin);
        $domainA = $this->domainWithId('11111111-1111-1111-1111-111111111111');
        $domainB = $this->domainWithId('22222222-2222-2222-2222-222222222222');
        $this->activeDomainResolver->method('getSelectableDomains')->willReturn([$domainA, $domainB]);
        $this->twig->expects($this->never())->method('render');

        $event = $this->buildEvent('/es/dashboard/products/manager');
        $this->setRoute($event, 'dashboard_products_manager');
        $event->getRequest()->getSession()->set(ActiveDashboardDomainResolver::SESSION_KEY, '11111111-1111-1111-1111-111111111111');

        $this->listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }
}
