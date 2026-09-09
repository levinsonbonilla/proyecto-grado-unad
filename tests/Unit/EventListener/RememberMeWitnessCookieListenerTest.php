<?php

namespace App\Tests\Unit\EventListener;

use App\EventListener\RememberMeCookieGateListener;
use App\EventListener\RememberMeWitnessCookieListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class RememberMeWitnessCookieListenerTest extends TestCase
{
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(HttpKernelInterface::class);
    }

    private function buildEvent(string $host, Response $response, bool $isMainRequest = true): ResponseEvent
    {
        $request = Request::create($host . '/es/login');
        return new ResponseEvent(
            $this->kernel,
            $request,
            $isMainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
            $response
        );
    }

    private function findCookie(Response $response, string $name): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }
        return null;
    }

    public function testAddsWitnessCookieWhenRememberMeIsSet(): void
    {
        $listener = new RememberMeWitnessCookieListener('');
        $response = new Response();
        $response->headers->setCookie(Cookie::create('REMEMBERME', 'some-token', time() + 31536000));

        $listener->onKernelResponse($this->buildEvent('http://localhost:8060', $response));

        $witness = $this->findCookie($response, RememberMeCookieGateListener::witnessCookieName('localhost:8060'));
        $this->assertNotNull($witness);
        $this->assertSame('1', $witness->getValue());
    }

    public function testClearsWitnessCookieWhenRememberMeIsCleared(): void
    {
        $listener = new RememberMeWitnessCookieListener('');
        $response = new Response();
        $response->headers->setCookie(Cookie::create('REMEMBERME', null, 1));

        $listener->onKernelResponse($this->buildEvent('http://localhost:8060', $response));

        $witness = $this->findCookie($response, RememberMeCookieGateListener::witnessCookieName('localhost:8060'));
        $this->assertNotNull($witness);
        $this->assertTrue($witness->isCleared());
    }

    public function testDoesNothingWhenNoRememberMeCookieInResponse(): void
    {
        $listener = new RememberMeWitnessCookieListener('');
        $response = new Response();

        $listener->onKernelResponse($this->buildEvent('http://localhost:8060', $response));

        $this->assertCount(0, $response->headers->getCookies());
    }

    public function testDoesNothingWhenSessionCookieDomainIsConfigured(): void
    {
        $listener = new RememberMeWitnessCookieListener('.proyecto-grado-unad.app');
        $response = new Response();
        $response->headers->setCookie(Cookie::create('REMEMBERME', 'shared-token', time() + 31536000));

        $listener->onKernelResponse($this->buildEvent('http://tenant1.proyecto-grado-unad.app', $response));

        $this->assertCount(1, $response->headers->getCookies());
    }

    public function testDoesNothingForSubRequests(): void
    {
        $listener = new RememberMeWitnessCookieListener('');
        $response = new Response();
        $response->headers->setCookie(Cookie::create('REMEMBERME', 'some-token', time() + 31536000));

        $listener->onKernelResponse($this->buildEvent('http://localhost:8060', $response, isMainRequest: false));

        $this->assertCount(1, $response->headers->getCookies());
    }
}
