<?php

namespace App\Controller\Dashboard;

use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Dashboard\Contact\EditContactInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '{_locale<%supported_locales%>}/dashboard/configurations/contact', name: 'dashboard_configurations_contact')]
final class ContactSettingsController extends AbstractController
{
    #[Route('', name: '', methods: ['GET', 'POST'])]
    public function edit(GetDomainDataInterface $getDomainData, EditContactInterface $editContact): Response
    {
        $process = $editContact->handler($getDomainData->getDomain());
        if ($process->isProcess()) {
            $this->addFlash($process->getFlashType(), $process->getMessage());
        }
        return $this->render('dashboard/contact_settings/contact.html.twig', [
            'form' => $process->getForm(),
        ]);
    }
}
