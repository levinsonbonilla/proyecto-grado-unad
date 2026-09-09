<?php

namespace App\Service\Payments;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Service\Geo\GeoLocation;

interface PaymentMethodAvailabilityResolverInterface
{

    public function resolve(Domains $domain, GeoLocation $geo): array;

    public function isAvailable(PaymentMethods $paymentMethod, GeoLocation $geo): bool;
}
