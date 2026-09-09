<?php

namespace App\Controller\Modules\Products;

use App\Entity\Products\Products;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Modules\Products\Manager\AddManagerInterface;
use App\Interface\UseCase\Modules\Products\Manager\EditManagerInterface;
use App\Interface\UseCase\Modules\Products\Manager\ListManagerInterface;
use App\Interface\UseCase\Modules\Products\Manager\ToggleStatusManagerInterface;
use App\Repository\Products\Colors\ColorsRepository;
use App\Repository\Products\Medidas\MedidasRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '{_locale<%supported_locales%>}/dashboard/products', name: 'dashboard_products_manager')]
class ProductsController extends AbstractController
{
    public function __construct(
        private readonly ColorsRepository $colorsRepository,
        private readonly MedidasRepository $medidasRepository,
        private readonly GetDomainDataInterface $getDomainData,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('dashboard/modules/products/manager/list.html.twig');
    }

    #[Route('/new', name: '_new', methods: ['GET', 'POST'])]
    public function new(AddManagerInterface $addManager): Response
    {
        $process = $addManager->handler();
        if ($process->isProcess()) {
            $type = $process->getFlashType();
            $this->addFlash($type, $process->getMessage());
        }
        return $this->render('dashboard/modules/products/manager/add_products.html.twig', [
            'form' => $process->getForm(),
            'colors' => $this->colorsRepository->getActiveByDomain($this->getDomainData->getDomainCache()),
            'medidas' => $this->medidasRepository->getActiveByDomain($this->getDomainData->getDomainCache()),
        ]);
    }

    #[Route('/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Products $entity, EditManagerInterface $edit): Response
    {
        $process = $edit->handler($entity);
        if ($process->isProcess()) {
            $type = $process->getFlashType();
            $this->addFlash($type, $process->getMessage());
        }
        return $this->render('dashboard/modules/products/manager/add_products.html.twig', [
            'form' => $process->getForm(),
            'isEdit' => true,
            'colors' => $this->colorsRepository->getActiveByDomain($this->getDomainData->getDomainCache()),
            'medidas' => $this->medidasRepository->getActiveByDomain($this->getDomainData->getDomainCache()),
        ]);
    }

    #[Route('/{id}/toggle-status', name: '_toggle_status', methods: ['POST'])]
    public function toggleStatus(Products $entity, ToggleStatusManagerInterface $toggleStatus): Response
    {
        return $this->json($toggleStatus->handler($entity));
    }

    #[Route('/list', name: '_list', methods: ['POST', 'GET'])]
    public function listCategories(ListManagerInterface $list): Response
    {
        return $this->json($list->handler(), 200);
    }
}
