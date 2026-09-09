<?php

namespace App\Interface\Configuration;

use App\Entity\Tenants\Domains\Domains;
use Symfony\Component\HttpFoundation\Request;

interface ActiveDashboardDomainResolverInterface
{

    public function resolve(Request $request): ?Domains;

    public function getSelectableDomains(): array;

    public function assertUsable(Domains $domain): void;
}
