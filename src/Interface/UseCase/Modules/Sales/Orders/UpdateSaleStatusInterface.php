<?php

namespace App\Interface\UseCase\Modules\Sales\Orders;

use App\Entity\Products\Orders\Orders;

interface UpdateSaleStatusInterface
{
    public function handler(
        Orders $order,
        string $newStatusName,
        ?string $trackingNumber = null,
        ?string $trackingCarrier = null,
    ): array;
}
