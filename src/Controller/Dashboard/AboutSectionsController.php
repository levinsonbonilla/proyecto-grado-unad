<?php

namespace App\Controller\Dashboard;

use App\Entity\Tenants\Others\AboutSections;
use App\Interface\UseCase\Dashboard\AboutSections\AddAboutSectionInterface;
use App\Interface\UseCase\Dashboard\AboutSections\EditAboutSectionInterface;
use App\Interface\UseCase\Dashboard\AboutSections\ListAboutSectionsInterface;
use App\Interface\UseCase\Dashboard\AboutSections\ToggleStatusAboutSectionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '{_locale<%supported_locales%>}/dashboard/configurations/about', name: 'dashboard_configurations_about')]
final class AboutSectionsController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('dashboard/about_sections/list.html.twig');
    }

    #[Route('/new', name: '_new', methods: ['GET', 'POST'])]
    public function new(AddAboutSectionInterface $addSection): Response
    {
        $process = $addSection->handler();
        if ($process->isProcess()) {
            $this->addFlash($process->getFlashType(), $process->getMessage());
        }
        return $this->render('dashboard/about_sections/section.html.twig', [
            'form' => $process->getForm(),
        ]);
    }

    #[Route('/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(AboutSections $section, EditAboutSectionInterface $editSection): Response
    {
        $process = $editSection->handler($section);
        if ($process->isProcess()) {
            $this->addFlash($process->getFlashType(), $process->getMessage());
        }
        return $this->render('dashboard/about_sections/section.html.twig', [
            'form' => $process->getForm(),
            'isEdit' => true,
            'section' => $section,
        ]);
    }

    #[Route('/{id}/toggle-status', name: '_toggle_status', methods: ['POST'])]
    public function toggleStatus(AboutSections $section, ToggleStatusAboutSectionInterface $toggleStatus): Response
    {
        return $this->json($toggleStatus->handler($section));
    }

    #[Route('/list', name: '_list', methods: ['POST', 'GET'])]
    public function listSections(ListAboutSectionsInterface $list): Response
    {
        return $this->json($list->handler(), 200);
    }
}
