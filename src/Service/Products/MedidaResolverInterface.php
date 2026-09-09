<?php

namespace App\Service\Products;

use App\Entity\Products\Medidas\Medidas;
use App\Entity\Tenants\Domains\Domains;

interface MedidaResolverInterface
{

    public function resolve(Domains $domain, string $name): Medidas;
}
