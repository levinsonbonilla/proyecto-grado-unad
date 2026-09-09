<?php

namespace App\Service\Products;

use App\Entity\Products\Colors\Colors;
use App\Entity\Tenants\Domains\Domains;

interface ColorResolverInterface
{

    public function resolve(Domains $domain, string $name, ?string $hex): Colors;
}
