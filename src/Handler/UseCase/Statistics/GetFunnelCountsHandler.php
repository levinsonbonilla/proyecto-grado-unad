<?php

namespace App\Handler\UseCase\Statistics;

use App\Entity\Tenants\Domains\Domains;
use App\Repository\Products\Orders\OrdersRepository;
use App\Repository\Tenants\Statistics\StatisticsEventsRepository;
use App\Repository\Tenants\Statistics\StatisticsRepository;

final readonly class GetFunnelCountsHandler
{
    private const PRODUCT_PAGE_PATTERN = '%/product/%';
    private const ADD_TO_CART_EVENT = 'add_to_cart';

    public function __construct(
        private StatisticsRepository $statisticsRepository,
        private StatisticsEventsRepository $statisticsEventsRepository,
        private OrdersRepository $ordersRepository,
    ) {
    }

    public function handler(Domains $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $visited = $this->statisticsRepository->getDistinctIdentifiers($domain, $from, $to);

        $viewedProduct = array_intersect(
            $visited,
            $this->statisticsRepository->getDistinctIdentifiers($domain, $from, $to, self::PRODUCT_PAGE_PATTERN)
        );

        $addedToCart = array_intersect(
            $viewedProduct,
            $this->statisticsEventsRepository->getDistinctIdentifiersForEvent($domain, self::ADD_TO_CART_EVENT, $from, $to)
        );

        $purchased = $this->ordersRepository->getDistinctBuyerIds($domain, $from, $to);

        return [
            'visited'       => count($visited),
            'viewedProduct' => count($viewedProduct),
            'addedToCart'   => count($addedToCart),
            'purchased'     => count($purchased),
        ];
    }
}
