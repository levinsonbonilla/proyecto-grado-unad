<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\EventListener\StatisticsResponseListener;
use Symfony\Component\Uid\Uuid;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\LocationInterface;
use App\Message\Statistics\RecordStatisticsMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class StatisticsResponseListenerTest extends TestCase
{
    private MessageBusInterface&MockObject    $bus;
    private GetDomainDataInterface&MockObject $getDomainData;
    private LocationInterface&MockObject      $location;
    private Security&MockObject               $security;
    private StatisticsResponseListener        $listener;

    protected function setUp(): void
    {
        $this->bus          = $this->createMock(MessageBusInterface::class);
        $this->getDomainData= $this->createMock(GetDomainDataInterface::class);
        $this->location     = $this->createMock(LocationInterface::class);
        $this->security     = $this->createMock(Security::class);

        $domain = $this->createMock(Domains::class);
        $domain->method('getId')->willReturn(Uuid::fromString('550e8400-e29b-41d4-a716-446655440000'));
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $this->location->method('getCountry')->willReturn(null);
        $this->location->method('getRegion')->willReturn(null);
        $this->location->method('getCity')->willReturn(null);
        $this->security->method('getUser')->willReturn(null);

        $this->listener = new StatisticsResponseListener(
            $this->bus,
            $this->getDomainData,
            $this->location,
            $this->security,
        );
    }

    private function makeEvent(
        string $route,
        int $statusCode = 200,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): ResponseEvent {
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/es/shop', 'GET');
        $request->attributes->set('_route', $route);
        $response = new Response('', $statusCode);
        return new ResponseEvent($kernel, $request, $requestType, $response);
    }

    public function testSubrequestsAreIgnored(): void
    {
        $this->bus->expects($this->never())->method('dispatch');
        $this->listener->onKernelResponse($this->makeEvent('store_e_commerce_shop', 200, HttpKernelInterface::SUB_REQUEST));
    }

    public function testErrorResponsesAreIgnored(): void
    {
        $this->bus->expects($this->never())->method('dispatch');
        $this->listener->onKernelResponse($this->makeEvent('store_e_commerce_shop', 404));
    }

    public function testDashboardRoutesAreIgnored(): void
    {
        $this->bus->expects($this->never())->method('dispatch');
        $this->listener->onKernelResponse($this->makeEvent('dashboard_statistics'));
    }

    public function testPublicLangIsExcluded(): void
    {
        $this->bus->expects($this->never())->method('dispatch');
        $this->listener->onKernelResponse($this->makeEvent('public_lang'));
    }

    public function testStoreRouteDispatchesMessage(): void
    {
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(RecordStatisticsMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        $this->listener->onKernelResponse($this->makeEvent('store_e_commerce_shop'));
    }

    public function testPublicRouteDispatchesMessage(): void
    {
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->listener->onKernelResponse($this->makeEvent('public_privacy'));
    }

    public function testDispatchesUserIdWhenAuthenticated(): void
    {
        $user = $this->createMock(Users::class);
        $user->method('getId')->willReturn(Uuid::fromString('660e8400-e29b-41d4-a716-446655440001'));
        $this->security = $this->createMock(Security::class);
        $this->security->method('getUser')->willReturn($user);

        $this->listener = new StatisticsResponseListener(
            $this->bus,
            $this->getDomainData,
            $this->location,
            $this->security,
        );

        $captured = null;
        $this->bus->method('dispatch')->willReturnCallback(function ($message) use (&$captured) {
            $captured = $message;
            return new Envelope(new \stdClass());
        });

        $this->listener->onKernelResponse($this->makeEvent('store_e_commerce_shop'));

        $this->assertInstanceOf(RecordStatisticsMessage::class, $captured);
        $this->assertSame('660e8400-e29b-41d4-a716-446655440001', $captured->userId);
    }

    public function testDispatchesNullUserIdForAnonymousVisitor(): void
    {
        $captured = null;
        $this->bus->method('dispatch')->willReturnCallback(function ($message) use (&$captured) {
            $captured = $message;
            return new Envelope(new \stdClass());
        });

        $this->listener->onKernelResponse($this->makeEvent('store_e_commerce_shop'));

        $this->assertNull($captured->userId);
    }

    public function testGetDomainExceptionSilentlyAborts(): void
    {
        $this->getDomainData->method('getDomainCache')
            ->willThrowException(new \RuntimeException('No domain'));

        $this->bus->expects($this->never())->method('dispatch');
        $this->listener->onKernelResponse($this->makeEvent('store_e_commerce_shop'));
    }

    public function testGetSubscribedEventsReturnsKernelResponse(): void
    {
        $events = StatisticsResponseListener::getSubscribedEvents();
        $this->assertArrayHasKey('kernel.response', $events);
    }
}
