<?php

namespace App\Tests\Unit\Handler\Configuration;

use App\Handler\Configuration\SecurityProcessHandler;
use App\Interface\UseCase\Security\LogInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SecurityProcessHandlerTest extends TestCase
{
    private Security&MockObject $security;
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private LogInterface&MockObject $log;
    private ParameterBagInterface&MockObject $params;
    private RequestStack&MockObject $requestStack;
    private SecurityProcessHandler $handler;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->log = $this->createMock(LogInterface::class);
        $this->params = $this->createMock(ParameterBagInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);

        $this->handler = new SecurityProcessHandler(
            $this->security,
            $this->urlGenerator,
            $this->log,
            $this->params,
            $this->requestStack,
        );
    }

    private function mockCurrentRequestLocale(string $locale): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getLocale')->willReturn($locale);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
    }

    public function testRedirectLoginReturnsNullWhenNotAuthenticated(): void
    {
        $this->security->method('isGranted')->with('IS_AUTHENTICATED_FULLY')->willReturn(false);

        $this->assertNull($this->handler->redirectLogin());
    }

    public function testRedirectLoginToDashboardKeepsCurrentLocaleForAdmin(): void
    {
        $this->mockCurrentRequestLocale('es');
        $this->security->method('isGranted')->willReturnMap([
            ['IS_AUTHENTICATED_FULLY', null, true],
            ['ROLE_SUPER_ADMIN', null, false],
            ['ROLE_ADMIN', null, true],
        ]);
        $this->params->method('get')->with('number_of_days_of_log_permanence')->willReturn('30');
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('dashboard', ['_locale' => 'es'])
            ->willReturn('/es/dashboard');

        $result = $this->handler->redirectLogin();

        $this->assertNotNull($result);
        $this->assertSame('/es/dashboard', $result->getTargetUrl());
    }

    public function testRedirectLoginToDashboardKeepsCurrentLocaleForSuperAdmin(): void
    {
        $this->mockCurrentRequestLocale('en');
        $this->security->method('isGranted')->willReturnMap([
            ['IS_AUTHENTICATED_FULLY', null, true],
            ['ROLE_SUPER_ADMIN', null, true],
            ['ROLE_ADMIN', null, false],
        ]);
        $this->params->method('get')->willReturn('30');
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('dashboard', ['_locale' => 'en'])
            ->willReturn('/en/dashboard');

        $result = $this->handler->redirectLogin();

        $this->assertSame('/en/dashboard', $result->getTargetUrl());
    }

    public function testRedirectLoginToStoreKeepsCurrentLocaleForRegularUser(): void
    {
        $this->mockCurrentRequestLocale('br');
        $this->security->method('isGranted')->willReturnMap([
            ['IS_AUTHENTICATED_FULLY', null, true],
            ['ROLE_SUPER_ADMIN', null, false],
            ['ROLE_ADMIN', null, false],
        ]);
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('store', ['_locale' => 'br'])
            ->willReturn('/br');

        $result = $this->handler->redirectLogin();

        $this->assertSame('/br', $result->getTargetUrl());
    }

    public function testRedirectLoginDefaultsToSpanishWhenThereIsNoCurrentRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $this->security->method('isGranted')->willReturnMap([
            ['IS_AUTHENTICATED_FULLY', null, true],
            ['ROLE_SUPER_ADMIN', null, false],
            ['ROLE_ADMIN', null, false],
        ]);
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('store', ['_locale' => 'es'])
            ->willReturn('/es');

        $this->handler->redirectLogin();
    }

    public function testRedirectLogoutKeepsCurrentLocale(): void
    {
        $this->mockCurrentRequestLocale('en');
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('store', ['_locale' => 'en'])
            ->willReturn('/en');

        $result = $this->handler->RedirectLogout();

        $this->assertSame('/en', $result->getTargetUrl());
    }
}
