<?php

namespace App\Controller\Store;

use App\Entity\Products\Orders\Orders;
use App\Interface\UseCase\Store\Orders\CancelOrderInterface;
use App\Interface\UseCase\Store\Orders\GetOrderDetailInterface;
use App\Interface\UseCase\Store\Orders\GetUserOrdersInterface;
use App\Interface\UseCase\Store\Orders\RetractionOrderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route(path: '{_locale<%supported_locales%>}/orders', name: 'store_orders')]
class OrdersController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function list(GetUserOrdersInterface $getOrders): Response
    {
        return $this->render('e_commerce/theme_1/orders/list.html.twig', [
            'orders' => $getOrders->handler(),
        ]);
    }

    #[Route('/{id}', name: '_detail', methods: ['GET'])]
    public function detail(Orders $order, GetOrderDetailInterface $getDetail): Response
    {
        $data = $getDetail->handler($order);

        if (isset($data['error'])) {
            $this->addFlash('error', $data['error']);
            return $this->redirectToRoute('store_orders', ['_locale' => $this->getParameter('locale')]);
        }

        return $this->render('e_commerce/theme_1/orders/detail.html.twig', $data);
    }

    #[Route('/{id}/cancel', name: '_cancel', methods: ['POST'])]
    public function cancel(Orders $order, CancelOrderInterface $cancel): Response
    {
        $result = $cancel->handler($order);
        if ($result['success']) {
            $this->addFlash('success', 'Pedido cancelado correctamente.');
        } else {
            $this->addFlash('error', $result['message'] ?? 'No se pudo cancelar el pedido.');
        }
        return $this->redirectToRoute('store_orders_detail', [
            '_locale' => $this->getParameter('locale'),
            'id'      => (string) $order->getId(),
        ]);
    }

    #[Route('/{id}/retraction', name: '_retraction', methods: ['POST'])]
    public function retraction(Orders $order, RetractionOrderInterface $retraction): Response
    {
        $result = $retraction->handler($order);
        if ($result['success']) {
            $this->addFlash('success', 'Solicitud de retracto enviada.');
        } else {
            $this->addFlash('error', $result['message'] ?? 'No se pudo solicitar el retracto.');
        }
        return $this->redirectToRoute('store_orders_detail', [
            '_locale' => $this->getParameter('locale'),
            'id'      => (string) $order->getId(),
        ]);
    }
}
