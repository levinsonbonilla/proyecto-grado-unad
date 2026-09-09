<?php

namespace App\Controller\Modules\Products;

use App\Entity\Products\Colors\Colors;
use App\Interface\UseCase\Modules\Products\Colors\AddColorsInterface;
use App\Interface\UseCase\Modules\Products\Colors\EditColorsInterface;
use App\Interface\UseCase\Modules\Products\Colors\ListColorsInterface;
use App\Interface\UseCase\Modules\Products\Colors\ToggleStatusColorsInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '{_locale<%supported_locales%>}/dashboard/products/colors', name: 'dashboard_products_colors')]
class ColorsController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('dashboard/modules/products/colors/list.html.twig');
    }

    #[Route('/new', name: '_new', methods: ['GET', 'POST'])]
    public function new(AddColorsInterface $addColors): Response
    {
        $process = $addColors->handler();
        if ($process->isProcess()) {
            $this->addFlash($process->isError() ? 'error' : 'success', $process->getMessage());
        }
        return $this->render('dashboard/modules/products/colors/colors.html.twig', [
            'form' => $process->getForm(),
        ]);
    }

    #[Route('/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Colors $color, EditColorsInterface $editColors): Response
    {
        $process = $editColors->handler($color);
        if ($process->isProcess()) {
            $this->addFlash($process->isError() ? 'error' : 'success', $process->getMessage());
        }
        return $this->render('dashboard/modules/products/colors/colors.html.twig', [
            'form'   => $process->getForm(),
            'isEdit' => true,
        ]);
    }

    #[Route('/{id}/toggle-status', name: '_toggle_status', methods: ['POST'])]
    public function toggleStatus(Colors $color, ToggleStatusColorsInterface $toggleStatus): Response
    {
        return $this->json($toggleStatus->handler($color));
    }

    #[Route('/list', name: '_list', methods: ['POST', 'GET'])]
    public function listColors(ListColorsInterface $listColors): Response
    {
        return $this->json($listColors->handler(), 200);
    }
}
