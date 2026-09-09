<?php

namespace App\Controller;

use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\RegisterTenantInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '{_locale<%supported_locales%>}/', name: 'tenant_signup')]
class TenantSignupController extends AbstractController
{
    #[Route(path: 'comenzar', name: '')]
    public function __invoke(
        RegisterTenantInterface $registerTenant,
        GetDomainDataInterface $getDomainData,
    ): Response {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('store');
        }

        if (!$getDomainData->getTenantCache()->isPrincipal()) {
            return $this->redirectToRoute('store');
        }

        $process = $registerTenant->handler();
        if ($process->isProcess()) {
            $this->addFlash($process->getFlashType(), $process->getMessage());
        }

        return $this->render('security/register_tenant_form.html.twig', [
            'form' => $process->getForm(),
            'platform_base_domain' => $this->getParameter('app.platform_base_domain'),
        ]);
    }
}
