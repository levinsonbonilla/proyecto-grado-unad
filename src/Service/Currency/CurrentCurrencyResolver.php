<?php

namespace App\Service\Currency;

use App\Entity\Tenants\Domains\Domains;
use App\Interface\Service\Currency\CurrentCurrencyResolverInterface;

final class CurrentCurrencyResolver implements CurrentCurrencyResolverInterface
{
    public function resolve(Domains $domain): CurrencyFormat
    {
        return new CurrencyFormat('COP', '$', 0, '.', ',');
    }
}
