<?php

namespace App\Controller\Modules\Sales;

use App\ArgumentHandler\StatisticsDateRange;
use App\Entity\Products\Orders\Orders;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Modules\Sales\Orders\GetSaleDetailInterface;
use App\Interface\UseCase\Modules\Sales\Orders\ListSalesInterface;
use App\Interface\UseCase\Modules\Sales\Orders\UpdateSaleStatusInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Orders\OrdersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route(path: '{_locale<%supported_locales%>}/dashboard/sales/orders', name: 'dashboard_sales_orders')]
final class OrdersController extends AbstractController
{
    public function __construct(
        private readonly OrdersRepository $ordersRepository,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly LogInterface $log,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('dashboard/modules/sales/orders/list.html.twig');
    }

    #[Route('/list', name: '_list', methods: ['POST', 'GET'])]
    public function listItems(ListSalesInterface $list): Response
    {
        return $this->json($list->handler(), 200);
    }

    #[Route('/{id}', name: '_detail', methods: ['GET'])]
    public function detail(Orders $order, GetSaleDetailInterface $detail): Response
    {
        return $this->render('dashboard/modules/sales/orders/detail.html.twig', [
            'order'   => $order,
            'detail'  => $detail->handler($order),
        ]);
    }

    #[Route('/{id}/status', name: '_update_status', methods: ['POST'])]
    public function updateStatus(Orders $order, Request $request, UpdateSaleStatusInterface $update): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? $request->request->all();

        $result = $update->handler(
            $order,
            $data['status'] ?? '',
            $data['trackingNumber'] ?? null,
            $data['trackingCarrier'] ?? null,
        );

        return $this->json($result);
    }

    #[Route('/data/kpi', name: '_kpi', methods: ['GET'])]
    public function kpi(Request $request): JsonResponse
    {
        try {
            $domain = $this->getDomainData->getDomainCache();
            $range  = StatisticsDateRange::fromRequest($request->query->all());
            return $this->json($this->ordersRepository->getSalesKpiSummary($domain, $range->from, $range->to));
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return $this->json([]);
        }
    }
}
