<?php

namespace App\Interface\Service\Currency;

use App\Entity\Tenants\Domains\Domains;
use App\Service\Currency\CurrencyFormat;

interface CurrentCurrencyResolverInterface
{
    public function resolve(Domains $domain): CurrencyFormat;
}
