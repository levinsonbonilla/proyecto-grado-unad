<?php

namespace App\Controller\Modules\Products;

use App\Entity\Products\Medidas\Medidas;
use App\Interface\UseCase\Modules\Products\Medidas\AddMedidasInterface;
use App\Interface\UseCase\Modules\Products\Medidas\EditMedidasInterface;
use App\Interface\UseCase\Modules\Products\Medidas\ListMedidasInterface;
use App\Interface\UseCase\Modules\Products\Medidas\ToggleStatusMedidasInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '{_locale<%supported_locales%>}/dashboard/products/medidas', name: 'dashboard_products_medidas')]
class MedidasController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('dashboard/modules/products/medidas/list.html.twig');
    }

    #[Route('/new', name: '_new', methods: ['GET', 'POST'])]
    public function new(AddMedidasInterface $addMedidas): Response
    {
        $process = $addMedidas->handler();
        if ($process->isProcess()) {
            $this->addFlash($process->isError() ? 'error' : 'success', $process->getMessage());
        }
        return $this->render('dashboard/modules/products/medidas/medidas.html.twig', [
            'form' => $process->getForm(),
        ]);
    }

    #[Route('/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Medidas $medida, EditMedidasInterface $editMedidas): Response
    {
        $process = $editMedidas->handler($medida);
        if ($process->isProcess()) {
            $this->addFlash($process->isError() ? 'error' : 'success', $process->getMessage());
        }
        return $this->render('dashboard/modules/products/medidas/medidas.html.twig', [
            'form'   => $process->getForm(),
            'isEdit' => true,
        ]);
    }

    #[Route('/{id}/toggle-status', name: '_toggle_status', methods: ['POST'])]
    public function toggleStatus(Medidas $medida, ToggleStatusMedidasInterface $toggleStatus): Response
    {
        return $this->json($toggleStatus->handler($medida));
    }

    #[Route('/list', name: '_list', methods: ['POST', 'GET'])]
    public function listMedidas(ListMedidasInterface $listMedidas): Response
    {
        return $this->json($listMedidas->handler(), 200);
    }
}
