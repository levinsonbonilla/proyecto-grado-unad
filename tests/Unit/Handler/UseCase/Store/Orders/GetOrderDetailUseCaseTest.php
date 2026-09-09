<?php

namespace App\Tests\Unit\Handler\UseCase\Store\Orders;

use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Entity\Configurations\Globals\Status;
use App\Entity\Products\Orders\Orders;
use App\Entity\Users\Users;
use App\Handler\UseCase\Store\Orders\GetOrderDetailUseCase;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Orders\OrdersProductsRepository;
use App\Repository\Products\Orders\OrdersRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

class GetOrderDetailUseCaseTest extends TestCase
{
    private Security&MockObject $security;
    private OrdersRepository&MockObject $ordersRepository;
    private OrdersProductsRepository&MockObject $ordersProductsRepository;
    private LogInterface&MockObject $log;
    private RequestStack&MockObject $requestStack;
    private GetOrderDetailUseCase $useCase;

    protected function setUp(): void
    {
        $this->security                 = $this->createMock(Security::class);
        $this->ordersRepository         = $this->createMock(OrdersRepository::class);
        $this->ordersProductsRepository = $this->createMock(OrdersProductsRepository::class);
        $this->log = $this->createMock(LogInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);

        $this->useCase = new GetOrderDetailUseCase(
            $this->security,
            $this->ordersRepository,
            $this->ordersProductsRepository,
            $this->log,
            $this->requestStack,
        );
    }

    private function makeUser(): Users&MockObject
    {
        $user = $this->createMock(Users::class);
        $user->method('getId')->willReturn(Uuid::v4());
        return $user;
    }

    public function testHandlerReturnsDetailForOwner(): void
    {
        $user  = $this->makeUser();
        $order = $this->createMock(Orders::class);
        $order->method('getUser')->willReturn($user);

        $this->security->method('getUser')->willReturn($user);
        $this->ordersRepository->method('findDetailById')->willReturn([
            'id' => 'abc', 'totalAmount' => '5000', 'shippingAddress' => 'Calle 1', 'statusName' => 'Pendiente',
        ]);
        $this->ordersProductsRepository->method('findByOrder')->willReturn([
            ['productName' => 'Prod A', 'quantity' => '1', 'unitPrice' => '5000', 'totalPrice' => '5000'],
        ]);

        $result = $this->useCase->handler($order);

        $this->assertArrayHasKey('order', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertCount(1, $result['items']);
    }

    public function testHandlerFallsBackToOrderEntityDataWithAllTemplateKeysWhenDetailNotFound(): void
    {
        $user = $this->makeUser();

        $country = $this->createMock(Countries::class);
        $country->method('getName')->willReturn('Colombia');
        $region = $this->createMock(Regions::class);
        $region->method('getName')->willReturn('Cundinamarca');
        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn('Pendiente');

        $order = $this->createMock(Orders::class);
        $order->method('getUser')->willReturn($user);
        $order->method('getId')->willReturn(Uuid::v4());
        $order->method('getTotalAmount')->willReturn('5000');
        $order->method('getShippingAddress')->willReturn('Calle 1');
        $order->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $order->method('getStatus')->willReturn($status);
        $order->method('getShippingCountry')->willReturn($country);
        $order->method('getShippingRegion')->willReturn($region);
        $order->method('getTrackingNumber')->willReturn(null);
        $order->method('getTrackingCarrier')->willReturn(null);

        $this->security->method('getUser')->willReturn($user);
        $this->ordersRepository->method('findDetailById')->willReturn(null);
        $this->ordersProductsRepository->method('findByOrder')->willReturn([]);

        $result = $this->useCase->handler($order);

        $this->assertArrayHasKey('order', $result);
        foreach (['trackingNumber', 'trackingCarrier', 'countryName', 'regionName', 'statusName'] as $key) {
            $this->assertArrayHasKey($key, $result['order']);
        }
    }

    public function testHandlerReturnsErrorWhenUserDoesNotOwnOrder(): void
    {
        $owner     = $this->makeUser();
        $requester = $this->makeUser();
        $order     = $this->createMock(Orders::class);
        $order->method('getUser')->willReturn($owner);

        $this->security->method('getUser')->willReturn($requester);

        $result = $this->useCase->handler($order);
        $this->assertArrayHasKey('error', $result);
    }

    public function testHandlerReturnsErrorWhenNotAuthenticated(): void
    {
        $owner = $this->makeUser();
        $order = $this->createMock(Orders::class);
        $order->method('getUser')->willReturn($owner);

        $this->security->method('getUser')->willReturn(null);

        $result = $this->useCase->handler($order);
        $this->assertArrayHasKey('error', $result);
    }
}
