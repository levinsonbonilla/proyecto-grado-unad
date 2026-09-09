<?php

namespace App\Controller\Dashboard;

use App\ArgumentHandler\StatisticsDateRange;
use App\Handler\UseCase\Statistics\GetFunnelCountsHandler;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Orders\OrdersProductsRepository;
use App\Repository\Products\Orders\OrdersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '{_locale<%supported_locales%>}/dashboard/statistics/products',
    name: 'dashboard_statistics_products'
)]
class ProductStatisticsController extends AbstractController
{
    public function __construct(
        private readonly OrdersRepository $ordersRepository,
        private readonly OrdersProductsRepository $ordersProductsRepository,
        private readonly GetFunnelCountsHandler $getFunnelCounts,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly LogInterface $log,
    ) {}

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('dashboard/statistics/products.html.twig');
    }

    #[Route('/data/kpi', name: '_kpi', methods: ['GET'])]
    public function kpi(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->ordersRepository->getSalesKpiSummary($domain, $range->from, $range->to),
            $request
        );
    }

    #[Route('/data/funnel', name: '_funnel', methods: ['GET'])]
    public function funnel(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->getFunnelCounts->handler($domain, $range->from, $range->to),
            $request
        );
    }

    #[Route('/data/top-products', name: '_top_products', methods: ['GET'])]
    public function topProducts(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->ordersProductsRepository->getTopSellingProducts($domain, $range->from, $range->to),
            $request
        );
    }

    #[Route('/data/sales-by-day', name: '_sales_by_day', methods: ['GET'])]
    public function salesByDay(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->ordersRepository->getSalesByDay($domain, $range->from, $range->to),
            $request
        );
    }

    #[Route('/data/by-country', name: '_by_country', methods: ['GET'])]
    public function byCountry(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->ordersRepository->getSalesByCountry($domain, $range->from, $range->to, $request->getLocale()),
            $request
        );
    }

    #[Route('/data/by-region', name: '_by_region', methods: ['GET'])]
    public function byRegion(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->ordersRepository->getSalesByRegion($domain, $range->from, $range->to, $request->getLocale()),
            $request
        );
    }

    #[Route('/data/by-city', name: '_by_city', methods: ['GET'])]
    public function byCity(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->ordersRepository->getSalesByCity($domain, $range->from, $range->to, $request->getLocale()),
            $request
        );
    }

    #[Route('/data/avg-shipping-time', name: '_avg_shipping_time', methods: ['GET'])]
    public function avgShippingTime(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => ['avgHours' => $this->ordersRepository->getAverageShippingTimeHours($domain, $range->from, $range->to)],
            $request
        );
    }

    private function dataResponse(callable $query, Request $request): JsonResponse
    {
        try {
            $domain = $this->getDomainData->getDomainCache();
            $range  = StatisticsDateRange::fromRequest($request->query->all());
            return $this->json($query($domain, $range));
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return $this->json([]);
        }
    }
}
