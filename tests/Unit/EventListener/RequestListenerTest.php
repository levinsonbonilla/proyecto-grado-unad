<?php

namespace App\Tests\Unit\EventListener;

use App\EventListener\RequestListener;
use App\Interface\Configuration\LocationInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class RequestListenerTest extends TestCase
{
    private LocationInterface&MockObject $location;
    private HttpKernelInterface&MockObject $kernel;
    private RequestListener $listener;

    protected function setUp(): void
    {
        $this->location = $this->createMock(LocationInterface::class);
        $this->kernel = $this->createMock(HttpKernelInterface::class);
        $this->listener = new RequestListener($this->location);
    }

    private function buildEvent(
        string $pathAndQuery,
        ?string $routeLocale = null,
        bool $isMainRequest = true,
        ?string $route = null,
    ): RequestEvent {
        $request = Request::create('http://localhost' . $pathAndQuery);
        if ($routeLocale !== null) {
            $request->attributes->set('_locale', $routeLocale);
            $request->setLocale($routeLocale);
        }
        if ($route !== null) {
            $request->attributes->set('_route', $route);
        }
        $request->setSession(new Session(new MockArraySessionStorage()));

        return new RequestEvent(
            $this->kernel,
            $request,
            $isMainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST
        );
    }

    public function testDoesNothingForSubRequests(): void
    {
        $this->location->expects($this->never())->method('getLanguage');
        $event = $this->buildEvent('/es/login', routeLocale: 'es', isMainRequest: false);

        $this->listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testSkipsThePaymentWebhookRoute(): void
    {
        $this->location->expects($this->never())->method('getLanguage');
        $event = $this->buildEvent('/checkout/webhook', route: 'store_checkout_webhook_payment');

        $this->listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
        $this->assertFalse($event->getRequest()->getSession()->has('_locale'));
    }

    public function testLangQueryParamSetsSessionLocaleWithoutRedirect(): void
    {
        $event = $this->buildEvent('/es/login?lang=en', routeLocale: 'es');

        $this->listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
        $this->assertSame('en', $event->getRequest()->getSession()->get('_locale'));
    }

    public function testExplicitUrlLocaleWinsOnFirstHitOfANewSession(): void
    {
        $this->location->expects($this->never())->method('getLanguage');
        $event = $this->buildEvent('/es/login', routeLocale: 'es');

        $this->listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
        $this->assertSame('es', $event->getRequest()->getLocale());
        $session = $event->getRequest()->getSession();
        $this->assertSame('es', $session->get('_locale'));
        $this->assertSame('es', $session->get('change_language'));
    }

    public function testExplicitUrlLocaleOverridesStaleSessionLocale(): void
    {
        $event = $this->buildEvent('/es/login', routeLocale: 'es');
        $session = $event->getRequest()->getSession();
        $session->set('change_language', true);
        $session->set('_locale', 'en');

        $this->listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
        $this->assertSame('es', $event->getRequest()->getLocale());
        $this->assertSame('es', $session->get('_locale'));
    }

    public function testFirstVisitWithoutUrlLocaleRedirectsUsingGeoResolvedLanguage(): void
    {
        $this->location->method('getLanguage')->willReturn('en');
        $event = $this->buildEvent('/login-sin-locale', route: 'redirect_to_locale');

        $this->listener->onKernelRequest($event);

        $this->assertTrue($event->hasResponse());
        $this->assertSame('/en/login-sin-locale/', $event->getResponse()->getTargetUrl());
        $session = $event->getRequest()->getSession();
        $this->assertSame('en', $session->get('_locale'));
        $this->assertSame('en', $session->get('change_language'));
    }

    public function testFallsBackToSessionLocaleWhenRouteHasNoLocaleSegment(): void
    {
        $event = $this->buildEvent('/checkout/webhook-not-actually', route: 'some_non_localized_route');
        $session = $event->getRequest()->getSession();
        $session->set('change_language', true);
        $session->set('_locale', 'br');

        $this->listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
        $this->assertSame('br', $event->getRequest()->getLocale());
    }
}
