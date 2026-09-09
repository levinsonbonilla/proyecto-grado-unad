<?php

namespace App\Handler\UseCase\Store\Orders;

use App\Entity\Products\Orders\Orders;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Orders\RetractionOrderInterface;
use App\Repository\Configurations\Globals\StatusRepository;
use Symfony\Bundle\SecurityBundle\Security;

final class RetractionOrderUseCase implements RetractionOrderInterface
{
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

            if ($order->getStatus()->getName() !== 'Entregado') {
                return ['success' => false, 'message' => 'Solo se puede solicitar retracto en pedidos entregados.'];
            }

            $retractionStatus = $this->statusRepository->findOneBy(['name' => 'Retracto Solicitado', 'active' => true]);
            if ($retractionStatus === null) {
                return ['success' => false, 'message' => 'Estado de retracto no disponible.'];
            }

            $order->edit($retractionStatus);
            $this->em->add($order, true);

            return ['success' => true];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false, 'message' => 'Error al solicitar el retracto.'];
        }
    }
}
