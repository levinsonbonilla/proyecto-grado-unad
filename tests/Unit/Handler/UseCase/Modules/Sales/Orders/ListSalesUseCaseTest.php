<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Sales\Orders;

use App\Entity\Tenants\Domains\Domains;
use App\Handler\UseCase\Modules\Sales\Orders\ListSalesUseCase;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\ListDataTableInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Orders\OrdersRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ListSalesUseCaseTest extends TestCase
{
    private ListDataTableInterface&MockObject $listDataTable;
    private LogInterface&MockObject $log;
    private OrdersRepository&MockObject $ordersRepository;
    private GetDomainDataInterface&MockObject $getDomainData;
    private RequestStack&MockObject $requestStack;
    private ListSalesUseCase $useCase;

    protected function setUp(): void
    {
        $this->listDataTable   = $this->createMock(ListDataTableInterface::class);
        $this->log              = $this->createMock(LogInterface::class);
        $this->ordersRepository = $this->createMock(OrdersRepository::class);
        $this->getDomainData    = $this->createMock(GetDomainDataInterface::class);
        $this->requestStack     = $this->createMock(RequestStack::class);

        $request = $this->createMock(Request::class);
        $request->method('get')->with('status')->willReturn(null);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->useCase = new ListSalesUseCase(
            $this->listDataTable,
            $this->log,
            $this->ordersRepository,
            $this->getDomainData,
            $this->requestStack,
        );
    }

    public function testHandlerReturnsRequiredDataTableKeys(): void
    {
        $domain = $this->createMock(Domains::class);
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $this->ordersRepository->method('getList')
            ->willReturnOnConsecutiveCalls(['total' => '2'], [['id' => 'a'], ['id' => 'b']]);

        $this->listDataTable->method('getDraw')->willReturn('1');

        $result = $this->useCase->handler();

        $this->assertArrayHasKey('draw', $result);
        $this->assertArrayHasKey('recordsTotal', $result);
        $this->assertArrayHasKey('recordsFiltered', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(2, $result['data']);
    }

    public function testHandlerReturnsEmptyDataOnException(): void
    {
        $this->getDomainData->method('getDomainCache')->willThrowException(new \RuntimeException('Error'));
        $this->log->method('handler')->willReturn(null);

        $result = $this->useCase->handler();

        $this->assertArrayHasKey('data', $result);
        $this->assertSame([0], $result['data']);
    }
}
