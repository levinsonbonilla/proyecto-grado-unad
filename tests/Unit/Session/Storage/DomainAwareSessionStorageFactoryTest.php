<?php

namespace App\Tests\Unit\Session\Storage;

use App\Session\Storage\DomainAwareSessionStorageFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

class DomainAwareSessionStorageFactoryTest extends TestCase
{
    private SessionStorageFactoryInterface&MockObject $decorated;
    private SessionStorageInterface&MockObject $storage;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(SessionStorageInterface::class);
        $this->decorated = $this->createMock(SessionStorageFactoryInterface::class);
        $this->decorated->method('createStorage')->willReturn($this->storage);
    }

    public function testOverridesCookieNameWhenCookieDomainIsEmpty(): void
    {
        $factory = new DomainAwareSessionStorageFactory($this->decorated, '');

        $this->storage->expects($this->once())
            ->method('setName')
            ->with($this->callback(fn (string $name) => $name !== ''));

        $factory->createStorage(Request::create('http://localhost:8060/es/login'));
    }

    public function testDifferentHostsProduceDifferentCookieNames(): void
    {
        $capturedNames = [];
        $this->storage->method('setName')
            ->willReturnCallback(function (string $name) use (&$capturedNames) {
                $capturedNames[] = $name;
            });

        $factory = new DomainAwareSessionStorageFactory($this->decorated, '');
        $factory->createStorage(Request::create('http://localhost:8060/'));
        $factory->createStorage(Request::create('http://localhost:8090/'));

        $this->assertCount(2, $capturedNames);
        $this->assertNotSame($capturedNames[0], $capturedNames[1]);
    }

    public function testSameHostProducesSameCookieNameAcrossRequests(): void
    {
        $capturedNames = [];
        $this->storage->method('setName')
            ->willReturnCallback(function (string $name) use (&$capturedNames) {
                $capturedNames[] = $name;
            });

        $factory = new DomainAwareSessionStorageFactory($this->decorated, '');
        $factory->createStorage(Request::create('http://localhost:8060/es/login'));
        $factory->createStorage(Request::create('http://localhost:8060/es/dashboard'));

        $this->assertSame($capturedNames[0], $capturedNames[1]);
    }

    public function testDoesNotOverrideNameWhenCookieDomainIsConfigured(): void
    {
        $factory = new DomainAwareSessionStorageFactory($this->decorated, '.proyecto-grado-unad.app');

        $this->storage->expects($this->never())->method('setName');

        $factory->createStorage(Request::create('https://tenant1.proyecto-grado-unad.app/'));
    }

    public function testNullRequestIsNoop(): void
    {
        $factory = new DomainAwareSessionStorageFactory($this->decorated, '');

        $this->storage->expects($this->never())->method('setName');

        $factory->createStorage(null);
    }

    public function testReturnsTheDecoratedStorageInstance(): void
    {
        $factory = new DomainAwareSessionStorageFactory($this->decorated, '');

        $result = $factory->createStorage(Request::create('http://localhost:8060/'));

        $this->assertSame($this->storage, $result);
    }
}
