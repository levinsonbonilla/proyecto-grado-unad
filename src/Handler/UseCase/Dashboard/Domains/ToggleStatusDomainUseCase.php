<?php

namespace App\Handler\UseCase\Dashboard\Domains;

use App\Entity\Tenants\Domains\Domains;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Dashboard\Domains\ToggleStatusDomainInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Domains\DomainsRepository;

final class ToggleStatusDomainUseCase implements ToggleStatusDomainInterface
{
    public function __construct(
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly LogInterface $log,
        private readonly DomainsRepository $domainsRepository,
    ) {
    }

    public function handler(Domains $domain): array
    {
        try {
            $domain->isActive() ? $domain->deactivate() : $domain->activate();
            $this->customeEntityManager->add($domain, true);
            $this->domainsRepository->invalidateCacheDomain($domain->getDomain());
            $this->domainsRepository->invalidateActiveDomainsByTenantCache($domain->getTenant());
            return ['success' => true];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false];
        }
    }
}
