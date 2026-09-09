<?php

namespace App\Tests\Unit\EventListener;

use App\EventListener\RememberMeCookieGateListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class RememberMeCookieGateListenerTest extends TestCase
{
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(HttpKernelInterface::class);
    }

    private function buildEvent(string $host, array $cookies, bool $isMainRequest = true): RequestEvent
    {
        $request = Request::create($host . '/es/dashboard');
        foreach ($cookies as $name => $value) {
            $request->cookies->set($name, $value);
        }
        return new RequestEvent(
            $this->kernel,
            $request,
            $isMainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST
        );
    }

    public function testRemovesRememberMeCookieWhenWitnessIsMissing(): void
    {
        $listener = new RememberMeCookieGateListener('');
        $event = $this->buildEvent('http://localhost:8090', ['REMEMBERME' => 'some-token-from-another-host']);

        $listener->onKernelRequest($event);

        $this->assertFalse($event->getRequest()->cookies->has('REMEMBERME'));
    }

    public function testKeepsRememberMeCookieWhenWitnessMatches(): void
    {
        $listener = new RememberMeCookieGateListener('');
        $host = 'http://localhost:8060';
        $witnessName = RememberMeCookieGateListener::witnessCookieName('localhost:8060');
        $event = $this->buildEvent($host, ['REMEMBERME' => 'valid-token', $witnessName => '1']);

        $listener->onKernelRequest($event);

        $this->assertTrue($event->getRequest()->cookies->has('REMEMBERME'));
    }

    public function testDoesNothingWhenNoRememberMeCookiePresent(): void
    {
        $listener = new RememberMeCookieGateListener('');
        $event = $this->buildEvent('http://localhost:8090', []);

        $listener->onKernelRequest($event);

        $this->assertFalse($event->getRequest()->cookies->has('REMEMBERME'));
    }

    public function testDoesNothingWhenSessionCookieDomainIsConfigured(): void
    {
        $listener = new RememberMeCookieGateListener('.proyecto-grado-unad.app');
        $event = $this->buildEvent('http://tenant1.proyecto-grado-unad.app', ['REMEMBERME' => 'shared-token']);

        $listener->onKernelRequest($event);

        $this->assertTrue($event->getRequest()->cookies->has('REMEMBERME'));
    }

    public function testDoesNothingForSubRequests(): void
    {
        $listener = new RememberMeCookieGateListener('');
        $event = $this->buildEvent('http://localhost:8090', ['REMEMBERME' => 'some-token'], isMainRequest: false);

        $listener->onKernelRequest($event);

        $this->assertTrue($event->getRequest()->cookies->has('REMEMBERME'));
    }
}
