<?php

namespace App\EventListener;

use App\Entity\Products\Orders\Orders;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Repository\Products\Orders\OrdersRepository;
use App\Repository\Users\UsersDomainsRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class TenantResourceGuardListener
{
    private const DASHBOARD_ROUTE_PREFIX = 'dashboard_';
    private const DOMAIN_MANAGEMENT_ROUTE_PREFIX = 'dashboard_configurations_domains';
    private const ENTITY_NAMESPACE = 'App\\Entity\\';

    public function __construct(
        private GetDomainDataInterface $getDomainData,
        private OrdersRepository $ordersRepository,
        private UsersDomainsRepository $usersDomainsRepository,
        private Security $security,
    ) {
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = (string) $event->getRequest()->attributes->get('_route', '');
        $activeDomain = null;

        foreach ($event->getArguments() as $argument) {
            if (!is_object($argument) || !$this->isTenantScoped($argument, $route)) {
                continue;
            }

            $activeDomain ??= $this->getDomainData->getDomainCache();

            if (!$this->belongsToActiveTenant($argument, $activeDomain)) {
                throw str_starts_with($route, self::DASHBOARD_ROUTE_PREFIX)
                    ? new AccessDeniedHttpException()
                    : new NotFoundHttpException();
            }
        }
    }

    private function isTenantScoped(object $argument, string $route): bool
    {
        if (!str_starts_with($argument::class, self::ENTITY_NAMESPACE)) {
            return false;
        }

        return match (true) {
            $argument instanceof Users => str_starts_with($route, self::DASHBOARD_ROUTE_PREFIX),
            $argument instanceof Domains => str_starts_with($route, self::DOMAIN_MANAGEMENT_ROUTE_PREFIX)
                && !$this->security->isGranted('ROLE_SUPER_ADMIN'),
            $argument instanceof Orders => true,
            default => method_exists($argument, 'getDomain'),
        };
    }

    private function belongsToActiveTenant(object $argument, Domains $activeDomain): bool
    {
        return match (true) {
            $argument instanceof Users => $this->usersDomainsRepository->count(['user' => $argument, 'domain' => $activeDomain]) > 0,
            $argument instanceof Domains => $this->currentUserAdministersTenantOf($argument),
            $argument instanceof Orders => $this->ordersRepository->belongsToDomain($argument, $activeDomain),
            default => $this->ownerDomainMatches($argument->getDomain(), $activeDomain),
        };
    }

    private function currentUserAdministersTenantOf(Domains $domain): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof Users) {
            return false;
        }

        foreach ($user->getUserDomainsActives() as $usersDomains) {
            if (in_array('ROLE_ADMIN', $usersDomains->getRoles() ?? [], true)
                && $usersDomains->getDomain()->getTenant()->getId()->equals($domain->getTenant()->getId())) {
                return true;
            }
        }

        return false;
    }

    private function ownerDomainMatches(mixed $ownerDomain, Domains $activeDomain): bool
    {
        return $ownerDomain instanceof Domains && $ownerDomain->getId()->equals($activeDomain->getId());
    }
}
