<?php

namespace App\Handler\UseCase\Dashboard\Users;

use App\Entity\Tenants\Tenants;
use App\Interface\UseCase\Dashboard\Users\GetDomainsInterface;
use App\Repository\Tenants\Domains\DomainsRepository;

final class GetDomainsUseCase implements GetDomainsInterface
{
    public function __construct(
        private readonly DomainsRepository $domainsRepository
    )
    {        
    }

    public function handler(Tenants $tenant): array
    {
        return $this->domainsRepository->getDomainsByTenant($tenant);        
    }
    
}
