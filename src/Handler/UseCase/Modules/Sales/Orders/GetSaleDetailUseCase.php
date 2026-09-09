<?php

namespace App\Handler\UseCase\Modules\Sales\Orders;

use App\Entity\Products\Orders\Orders;
use App\Interface\UseCase\Modules\Sales\Orders\GetSaleDetailInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Products\Orders\OrdersProductsRepository;
use App\Repository\Products\Orders\OrdersRepository;
use App\Repository\Products\Orders\PaymentTransactionsRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final class GetSaleDetailUseCase implements GetSaleDetailInterface
{
    public function __construct(
        private readonly OrdersRepository $ordersRepository,
        private readonly OrdersProductsRepository $ordersProductsRepository,
        private readonly PaymentTransactionsRepository $paymentTransactionsRepository,
        private readonly LogInterface $log,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function handler(Orders $order): array
    {
        try {
            $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'es';
            $detail = $this->ordersRepository->getAdminDetail($order, $locale);
            if ($detail === null) {
                return [];
            }

            $transaction = $this->paymentTransactionsRepository->findLatestByOrder($order);

            return [
                'order'       => $detail,
                'items'       => $this->ordersProductsRepository->findByOrder($order),
                'transaction' => $transaction === null ? null : [
                    'amount'           => $transaction->getAmount(),
                    'statusName'       => $transaction->getStatus()->getName(),
                    'paymentMethodName' => $transaction->getPaymentMethod()?->getName(),
                    'gatewayReference' => $transaction->getGatewayReference(),
                ],
            ];
        } catch (\Throwable $th) {
            $this->log->handler(throwable: $th);
            return [];
        }
    }
}
