<?php

namespace App\Handler\UseCase\Store\Orders;

use App\Entity\Products\Orders\Orders;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Orders\CancelOrderInterface;
use App\Service\Orders\OrderCancellationInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CancelOrderUseCase implements CancelOrderInterface
{
    private const CANCELLABLE_STATUSES = ['Pendiente', 'Procesando'];

    public function __construct(
        private readonly Security $security,
        private readonly OrderCancellationInterface $orderCancellation,
        private readonly LogInterface $log,
        private readonly TranslatorInterface $translator,
    ) {}

    public function handler(Orders $order): array
    {
        try {
            $user = $this->security->getUser();
            if ($user === null || (string) $order->getUser()->getId() !== (string) $user->getId()) {
                return ['success' => false, 'message' => $this->translator->trans('order_cancel_unauthorized', [], 'store')];
            }

            if (!in_array($order->getStatus()->getName(), self::CANCELLABLE_STATUSES, true)
                || !$this->orderCancellation->cancel($order, self::CANCELLABLE_STATUSES)) {
                return ['success' => false, 'message' => $this->translator->trans('order_cancel_not_allowed', [], 'store')];
            }

            return ['success' => true];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false, 'message' => $this->translator->trans('order_cancel_error', [], 'store')];
        }
    }
}
