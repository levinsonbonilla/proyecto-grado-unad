<?php

namespace App\EventListener;

use App\Entity\Users\Users;
use App\Handler\Configuration\ActiveDashboardDomainResolver;
use App\Interface\Configuration\ActiveDashboardDomainResolverInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class ActiveDomainGuardListener
{

    private const ALLOWED_ROUTE_PREFIXES = [
        'dashboard_configurations_domains',
        'dashboard_tenants',
        'dashboard_profile',
        'dashboard_users_profile',
        'dashboard_domain_switch',
        'security_logout',
        'dashboard_help',
        'dashboard_messages',
    ];

    private const SUPER_ADMIN_ONLY_ALLOWED_ROUTE_PREFIXES = [
        'dashboard_users',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly ActiveDashboardDomainResolverInterface $activeDomainResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_contains($request->getPathInfo(), '/dashboard')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof Users) {
            return;
        }

        $route = (string) $request->attributes->get('_route', '');
        $isSuperAdmin = in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);
        if ($this->isAllowedRoute($route) || ($isSuperAdmin && $this->isSuperAdminOnlyAllowedRoute($route))) {
            return;
        }

        $selectable = $this->activeDomainResolver->getSelectableDomains();
        $count = count($selectable);

        if ($count === 0) {
            $event->setResponse(new RedirectResponse(
                $this->urlGenerator->generate('dashboard_configurations_domains_new', [
                    '_locale' => $request->getLocale(),
                ])
            ));
            return;
        }

        $session = $request->getSession();

        if ($count === 1) {
            if ($session->get(ActiveDashboardDomainResolver::SESSION_KEY) === null) {
                $session->set(ActiveDashboardDomainResolver::SESSION_KEY, (string) $selectable[0]->getId());
            }
            return;
        }

        if ($session->get(ActiveDashboardDomainResolver::SESSION_KEY) === null) {
            $content = $this->twig->render('dashboard/domain_switch/select.html.twig', [
                'domains' => $selectable,
            ]);
            $event->setResponse(new Response($content));
        }
    }

    private function isAllowedRoute(string $route): bool
    {
        foreach (self::ALLOWED_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private function isSuperAdminOnlyAllowedRoute(string $route): bool
    {
        foreach (self::SUPER_ADMIN_ONLY_ALLOWED_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
