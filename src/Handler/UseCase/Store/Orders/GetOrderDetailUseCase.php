<?php

namespace App\Handler\UseCase\Store\Orders;

use App\Entity\Products\Orders\Orders;
use App\Exception\GenericException;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Orders\GetOrderDetailInterface;
use App\Repository\Products\Orders\OrdersProductsRepository;
use App\Repository\Products\Orders\OrdersRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class GetOrderDetailUseCase implements GetOrderDetailInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly OrdersRepository $ordersRepository,
        private readonly OrdersProductsRepository $ordersProductsRepository,
        private readonly LogInterface $log,
        private readonly RequestStack $requestStack,
    ) {}

    public function handler(Orders $order): array
    {
        try {
            $user = $this->security->getUser();

            if ($user === null || (string) $order->getUser()->getId() !== (string) $user->getId()) {
                throw new GenericException('No autorizado.', 403);
            }

            $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'es';
            $detail = $this->ordersRepository->findDetailById((string) $order->getId(), $user, $locale);
            $items  = $this->ordersProductsRepository->findByOrder($order);

            return [
                'order' => $detail ?? [
                    'id'              => (string) $order->getId(),
                    'totalAmount'     => $order->getTotalAmount(),
                    'shippingAddress' => $order->getShippingAddress(),
                    'createdAt'       => $order->getCreatedAt(),
                    'statusName'      => $order->getStatus()->getName(),
                    'countryName'     => $order->getShippingCountry()->getName($locale),
                    'regionName'      => $order->getShippingRegion()->getName($locale),
                    'cityNames'       => null,
                    'trackingNumber'  => $order->getTrackingNumber(),
                    'trackingCarrier' => $order->getTrackingCarrier(),
                ],
                'items' => $items,
            ];
        } catch (GenericException $e) {
            $this->log->handler($e);
            return ['error' => $e->getMessage(), 'code' => $e->getCode()];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['error' => 'Error al cargar el pedido.'];
        }
    }
}
