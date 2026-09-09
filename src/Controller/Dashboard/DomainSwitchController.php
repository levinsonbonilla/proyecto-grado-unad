<?php

namespace App\Controller\Dashboard;

use App\Entity\Tenants\Domains\Domains;
use App\Handler\Configuration\ActiveDashboardDomainResolver;
use App\Interface\Configuration\ActiveDashboardDomainResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '{_locale<%supported_locales%>}/dashboard/domain', name: 'dashboard_domain_switch')]
class DomainSwitchController extends AbstractController
{
    #[Route('/{id}/switch', name: '', methods: ['GET'])]
    public function __invoke(
        Domains $domain,
        Request $request,
        ActiveDashboardDomainResolverInterface $activeDomainResolver,
    ): Response {

        $activeDomainResolver->assertUsable($domain);
        $request->getSession()->set(ActiveDashboardDomainResolver::SESSION_KEY, (string) $domain->getId());

        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?? $this->generateUrl('dashboard', [
            '_locale' => $request->getLocale(),
        ]));
    }
}
