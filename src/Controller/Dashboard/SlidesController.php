<?php

namespace App\Controller\Dashboard;

use App\Entity\Tenants\Others\Slides;
use App\Form\Dashboard\SlidesType;
use App\Interface\UseCase\Dashboard\Slides\AddSlideInterface;
use App\Interface\UseCase\Dashboard\Slides\DeleteSlideInterface;
use App\Interface\UseCase\Dashboard\Slides\EditSlideInterface;
use App\Interface\UseCase\Dashboard\Slides\ListSlidesInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '{_locale<%supported_locales%>}/dashboard/configurations/slides', name: 'dashboard_configurations_slides')]
final class SlidesController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('dashboard/slides/list.html.twig');
    }

    #[Route('/new', name: '_new', methods: ['GET', 'POST'])]
    public function new(AddSlideInterface $addSlide): Response
    {
        $process = $addSlide->handler(SlidesType::class);
        if ($process->isProcess()) {
            $type = $process->getFlashType();
            $this->addFlash($type, $process->getMessage());
        }
        return $this->render('dashboard/slides/slide.html.twig', [
            'form' => $process->getForm()
        ]);
    }

    #[Route('/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Slides $slide, EditSlideInterface $editSlide): Response
    {
        $process = $editSlide->handler($slide);
        if ($process->isProcess()) {
            $type = $process->getFlashType();
            $this->addFlash($type, $process->getMessage());
        }
        return $this->render('dashboard/slides/slide.html.twig', [
            'form' => $process->getForm(),
            'isEdit' => true,
            'slide' => $slide
        ]);
    }

    #[Route('/{id}/delete', name: '_delete', methods: ['POST'])]
    public function delete(Slides $slide, DeleteSlideInterface $deleteSlide): Response
    {
        return $this->json($deleteSlide->handler($slide));
    }

    #[Route('/list', name: '_list', methods: ['POST', 'GET'])]
    public function listSlides(ListSlidesInterface $list): Response
    {
        return $this->json($list->handler(), 200);
    }

}
