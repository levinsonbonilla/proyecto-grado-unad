<?php

namespace App\Tests\Unit\Handler\UseCase\Statistics;

use App\Entity\Tenants\Domains\Domains;
use App\Handler\UseCase\Statistics\GetFunnelCountsHandler;
use App\Repository\Products\Orders\OrdersRepository;
use App\Repository\Tenants\Statistics\StatisticsEventsRepository;
use App\Repository\Tenants\Statistics\StatisticsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetFunnelCountsHandlerTest extends TestCase
{
    private StatisticsRepository&MockObject $statisticsRepository;
    private StatisticsEventsRepository&MockObject $statisticsEventsRepository;
    private OrdersRepository&MockObject $ordersRepository;
    private GetFunnelCountsHandler $handler;
    private Domains $domain;
    private \DateTimeImmutable $from;
    private \DateTimeImmutable $to;

    protected function setUp(): void
    {
        $this->statisticsRepository = $this->createMock(StatisticsRepository::class);
        $this->statisticsEventsRepository = $this->createMock(StatisticsEventsRepository::class);
        $this->ordersRepository = $this->createMock(OrdersRepository::class);

        $this->handler = new GetFunnelCountsHandler(
            $this->statisticsRepository,
            $this->statisticsEventsRepository,
            $this->ordersRepository,
        );

        $this->domain = $this->createMock(Domains::class);
        $this->from = new \DateTimeImmutable('-1 day');
        $this->to = new \DateTimeImmutable('+1 day');
    }

    public function testHandlerIntersectsConsecutiveStagesStrictly(): void
    {
        $this->statisticsRepository->method('getDistinctIdentifiers')
            ->willReturnCallback(function ($domain, $from, $to, $pagePattern = null) {
                if ($pagePattern === null) {
                    return ['A', 'B', 'C'];
                }
                return ['A', 'B', 'D'];
            });

        $this->statisticsEventsRepository->method('getDistinctIdentifiersForEvent')
            ->willReturn(['A', 'D']);

        $this->ordersRepository->method('getDistinctBuyerIds')
            ->willReturn(['user-a-id', 'user-z-id']);

        $result = $this->handler->handler($this->domain, $this->from, $this->to);

        $this->assertSame(3, $result['visited']);
        $this->assertSame(2, $result['viewedProduct']);
        $this->assertSame(1, $result['addedToCart']);
        $this->assertSame(2, $result['purchased']);
    }

    public function testHandlerReturnsZeroesWhenNoDataInRange(): void
    {
        $this->statisticsRepository->method('getDistinctIdentifiers')->willReturn([]);
        $this->statisticsEventsRepository->method('getDistinctIdentifiersForEvent')->willReturn([]);
        $this->ordersRepository->method('getDistinctBuyerIds')->willReturn([]);

        $result = $this->handler->handler($this->domain, $this->from, $this->to);

        $this->assertSame(['visited' => 0, 'viewedProduct' => 0, 'addedToCart' => 0, 'purchased' => 0], $result);
    }

    public function testHandlerReturnsAllFourExpectedKeys(): void
    {
        $this->statisticsRepository->method('getDistinctIdentifiers')->willReturn([]);
        $this->statisticsEventsRepository->method('getDistinctIdentifiersForEvent')->willReturn([]);
        $this->ordersRepository->method('getDistinctBuyerIds')->willReturn([]);

        $result = $this->handler->handler($this->domain, $this->from, $this->to);

        foreach (['visited', 'viewedProduct', 'addedToCart', 'purchased'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }
}
