<?php

namespace App\Handler\Configuration;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Entity\Users\UsersDomains;
use App\Exception\GenericException;
use App\Interface\Configuration\ActiveDashboardDomainResolverInterface;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\Repository\Tenants\TenantsRepository;
use App\Util\StringUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class ActiveDashboardDomainResolver implements ActiveDashboardDomainResolverInterface
{
    public const SESSION_KEY = 'dashboard_active_domain_id';

    public function __construct(
        private Security $security,
        private DomainsRepository $domainsRepository,
        private TenantsRepository $tenantsRepository,
        private RequestStack $requestStack,
    ) {
    }

    #[\Override]
    public function resolve(Request $request): ?Domains
    {

        $user = $this->security->getUser();
        if (!$user instanceof Users) {
            return null;
        }

        $selectedId = $request->getSession()->get(self::SESSION_KEY);
        if ($selectedId === null) {
            return null;
        }

        $domain = $this->domainsRepository->find($selectedId);
        if ($domain === null || !$this->userCanUseDomain($user, $domain)) {

            $request->getSession()->remove(self::SESSION_KEY);
            return null;
        }

        return $domain;
    }

    #[\Override]
    public function getSelectableDomains(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof Users) {
            return [];
        }

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            $tenant = $this->resolveCurrentHostTenant();
            return $tenant === null ? [] : $this->domainsRepository->getActiveDomainsByTenant($tenant);
        }

        $domains = [];
        foreach ($user->getUserDomainsActives() as $usersDomains) {

            if (in_array('ROLE_ADMIN', $usersDomains->getRoles() ?? [], true)) {
                $domains[] = $usersDomains->getDomain();
            }
        }

        return $domains;
    }

    #[\Override]
    public function assertUsable(Domains $domain): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof Users || !$this->userCanUseDomain($user, $domain)) {
            throw new GenericException("Error Processing Request", 403);
        }
    }

    private function userCanUseDomain(Users $user, Domains $domain): bool
    {
        foreach ($this->getSelectableDomains() as $selectable) {
            if ((string) $selectable->getId() === (string) $domain->getId()) {
                return true;
            }
        }
        return false;
    }

    private function resolveCurrentHostTenant(): ?Tenants
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        $domain = $this->domainsRepository->getDomainCache(
            StringUtil::removeHTTP($request->getSchemeAndHttpHost())
        );

        return $domain === null ? null : $this->tenantsRepository->getTenantCache($domain->getTenant()->getId());
    }
}
