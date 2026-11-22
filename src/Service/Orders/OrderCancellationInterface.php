<?php

namespace App\Service\Orders;

use App\Entity\Products\Orders\Orders;

interface OrderCancellationInterface
{
    public const CANCELLED_STATUS = 'Cancelado';

    public function cancel(Orders $order, array $cancellableStatuses): bool;
}
