<?php

namespace App\Controller\Dashboard;

use App\ArgumentHandler\StatisticsDateRange;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Statistics\StatisticsEventsRepository;
use App\Repository\Tenants\Statistics\StatisticsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '{_locale<%supported_locales%>}/dashboard/statistics',
    name: 'dashboard_statistics'
)]
class StatisticsController extends AbstractController
{
    public function __construct(
        private readonly StatisticsRepository       $statisticsRepository,
        private readonly StatisticsEventsRepository $statisticsEventsRepository,
        private readonly GetDomainDataInterface      $getDomainData,
        private readonly LogInterface                $log,
    ) {}

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('dashboard/statistics/index.html.twig');
    }

    #[Route('/data/kpi', name: '_kpi', methods: ['GET'])]
    public function kpi(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->statisticsRepository->getKpiSummary($domain, $range->from, $range->to),
            $request
        );
    }

    #[Route('/data/visits-by-day', name: '_visits_by_day', methods: ['GET'])]
    public function visitsByDay(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->statisticsRepository->getVisitsByDay($domain, $range->from, $range->to),
            $request
        );
    }

    #[Route('/data/by-country', name: '_by_country', methods: ['GET'])]
    public function byCountry(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->statisticsRepository->getVisitsByCountry($domain, $range->from, $range->to),
            $request
        );
    }

    #[Route('/data/by-browser', name: '_by_browser', methods: ['GET'])]
    public function byBrowser(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->statisticsRepository->getTopBrowsers($domain, $range->from, $range->to),
            $request
        );
    }

    #[Route('/data/by-device', name: '_by_device', methods: ['GET'])]
    public function byDevice(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->statisticsRepository->getTopDevices($domain, $range->from, $range->to),
            $request
        );
    }

    #[Route('/data/by-os', name: '_by_os', methods: ['GET'])]
    public function byOs(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->statisticsRepository->getTopOperativeSystems($domain, $range->from, $range->to),
            $request
        );
    }

    #[Route('/data/by-page', name: '_by_page', methods: ['GET'])]
    public function byPage(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->statisticsRepository->getTopPages($domain, $range->from, $range->to),
            $request
        );
    }

    #[Route('/data/by-referrer', name: '_by_referrer', methods: ['GET'])]
    public function byReferrer(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->statisticsRepository->getTopReferrers($domain, $range->from, $range->to),
            $request
        );
    }

    #[Route('/data/by-auth-status', name: '_by_auth_status', methods: ['GET'])]
    public function byAuthStatus(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->statisticsRepository->getVisitorsByAuthStatus($domain, $range->from, $range->to),
            $request
        );
    }

    #[Route('/data/by-campaign', name: '_by_campaign', methods: ['GET'])]
    public function byCampaign(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->statisticsRepository->getTopCampaigns($domain, $range->from, $range->to),
            $request
        );
    }

    #[Route('/data/by-event', name: '_by_event', methods: ['GET'])]
    public function byEvent(Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->statisticsEventsRepository->getTopEvents($domain, $range->from, $range->to),
            $request
        );
    }

    #[Route('/data/event/{eventName}/by-day', name: '_event_by_day', methods: ['GET'])]
    public function eventByDay(string $eventName, Request $request): JsonResponse
    {
        return $this->dataResponse(
            fn($domain, $range) => $this->statisticsEventsRepository->getEventsByDay($domain, $eventName, $range->from, $range->to),
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
