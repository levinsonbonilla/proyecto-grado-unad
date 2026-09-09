<?php

namespace App\Interface\Configuration;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;

interface GetDomainDataInterface
{
    public function getDomain(): Domains;

    public function getDomainCache(): Domains;

    public function getTenantCache(): Tenants;

    public function getTenant(): Tenants;
}