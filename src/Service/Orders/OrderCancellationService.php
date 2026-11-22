<?php

namespace App\Service\Orders;

use App\Entity\Configurations\Globals\Status;
use App\Entity\Products\Orders\Orders;
use App\Entity\Products\Orders\OrdersProducts;
use App\Exception\GenericException;
use App\Service\Products\ProductStockInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrderCancellationService implements OrderCancellationInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProductStockInterface $productStock,
    ) {
    }

    public function cancel(Orders $order, array $cancellableStatuses): bool
    {
        $statusRepository = $this->em->getRepository(Status::class);
        $cancelStatus = $statusRepository->findOneBy(['name' => self::CANCELLED_STATUS, 'active' => true]);
        if ($cancelStatus === null) {
            throw new GenericException('Estado de cancelación no disponible.', 500);
        }

        $allowedIds = array_map(
            fn (Status $status) => $status->getId()->toBinary(),
            $statusRepository->findBy(['name' => $cancellableStatuses])
        );
        if ($allowedIds === []) {
            return false;
        }

        $orderId = $order->getId()->toBinary();
        $connection = $this->em->getConnection();

        $cancelled = $connection->transactional(function () use ($connection, $orderId, $cancelStatus, $allowedIds, $order): bool {
            $transitioned = $connection->executeStatement(
                'UPDATE orders SET status_id = ?, updated_at = ? WHERE id = ? AND status_id IN (?)',
                [$cancelStatus->getId()->toBinary(), $this->now(), $orderId, $allowedIds],
                [null, null, null, ArrayParameterType::STRING]
            );
            if ($transitioned === 0) {
                return false;
            }

            $claimed = $connection->executeStatement(
                'UPDATE orders SET stock_released_at = ? WHERE id = ? AND stock_released_at IS NULL',
                [$this->now(), $orderId]
            );
            if ($claimed === 1) {
                $lines = $this->em->getRepository(OrdersProducts::class)->findBy(['orders' => $order]);
                foreach ($lines as $line) {
                    $this->productStock->release($line->getProduct(), $line->getProductColor(), (int) $line->getQuantity());
                }
            }

            return true;
        });

        $this->em->refresh($order);
        foreach ($this->em->getRepository(OrdersProducts::class)->findBy(['orders' => $order]) as $line) {
            $this->em->refresh($line->getProduct());
            if ($line->getProductColor() !== null) {
                $this->em->refresh($line->getProductColor());
            }
        }

        return $cancelled;
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
