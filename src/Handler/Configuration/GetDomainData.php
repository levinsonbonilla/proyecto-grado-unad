<?php

namespace App\Handler\Configuration;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Exception\GenericException;
use App\Interface\Configuration\ActiveDashboardDomainResolverInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\Repository\Tenants\TenantsRepository;
use App\Util\StringUtil;
use Symfony\Component\HttpFoundation\RequestStack;


final readonly class GetDomainData implements GetDomainDataInterface
{
    public function __construct(
        private RequestStack $request,
        private DomainsRepository $domainsRepository,
        private TenantsRepository $tenantsRepository,
        private ActiveDashboardDomainResolverInterface $activeDomainResolver,
    ) {
    }

    #[\Override]
    public function getDomain(): Domains
    {
        $override = $this->resolveSessionOverride();
        if ($override !== null) {
            return $override;
        }

        $request = $this->request->getCurrentRequest();
        $domain = $this->domainsRepository->getDomain(
            StringUtil::removeHTTP($request->getSchemeAndHttpHost())
        );

        if (empty($domain)) {
            throw new GenericException("Error Processing Request", 404);
        }

        return $domain;
    }

    #[\Override]
    public function getDomainCache(): Domains
    {
        $override = $this->resolveSessionOverride();
        if ($override !== null) {
            return $override;
        }

        $request = $this->request->getCurrentRequest();
        $domain = $this->domainsRepository->getDomainCache(
            StringUtil::removeHTTP($request->getSchemeAndHttpHost())
        );

        if (empty($domain)) {
            throw new GenericException("Error Processing Request", 404);
        }

        return $domain;
    }

    
    private function resolveSessionOverride(): ?Domains
    {
        $request = $this->request->getCurrentRequest();
        if ($request === null || !str_contains($request->getPathInfo(), '/dashboard')) {
            return null;
        }

        return $this->activeDomainResolver->resolve($request);
    }


    #[\Override]
    public function getTenantCache(): Tenants
    {
        $domain = $this->getDomainCache();
        $tenant = $this->tenantsRepository->getTenantCache($domain->getTenant()->getId());
        if (empty($tenant)) {
            throw new GenericException("Error Processing Request", 404);            
        }

        return $tenant;
    }

    #[\Override]
    public function getTenant(): Tenants
    {
        $domain = $this->getDomainCache();
        $tenant = $this->tenantsRepository->getTenant($domain->getTenant()->getId());
        if (empty($tenant)) {
            throw new GenericException("Error Processing Request", 404);            
        }

        return $tenant;
    }   
}
