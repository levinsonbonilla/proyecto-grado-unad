<?php

namespace App\Handler\UseCase\Store\Orders;

use App\Entity\Products\Orders\Orders;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Orders\CancelOrderInterface;
use App\Repository\Configurations\Globals\StatusRepository;
use Symfony\Bundle\SecurityBundle\Security;

final class CancelOrderUseCase implements CancelOrderInterface
{
    private const CANCELLABLE_STATUSES = ['Pendiente', 'Procesando'];

    public function __construct(
        private readonly Security $security,
        private readonly StatusRepository $statusRepository,
        private readonly CustomeEntityManagerInterface $em,
        private readonly LogInterface $log,
    ) {}

    public function handler(Orders $order): array
    {
        try {
            $user = $this->security->getUser();
            if ($user === null || (string) $order->getUser()->getId() !== (string) $user->getId()) {
                return ['success' => false, 'message' => 'No autorizado.'];
            }

            if (!in_array($order->getStatus()->getName(), self::CANCELLABLE_STATUSES, true)) {
                return ['success' => false, 'message' => 'Este pedido no se puede cancelar en su estado actual.'];
            }

            $cancelStatus = $this->statusRepository->findOneBy(['name' => 'Cancelado', 'active' => true]);
            if ($cancelStatus === null) {
                return ['success' => false, 'message' => 'Estado de cancelación no disponible.'];
            }

            $order->edit($cancelStatus);
            $this->em->add($order, true);

            return ['success' => true];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false, 'message' => 'Error al cancelar el pedido.'];
        }
    }
}
