<?php

namespace App\Controller\Modules\Products;

use App\Entity\Products\Categories\Categories;
use App\Interface\UseCase\Modules\Products\Categories\AddCategoriesInterface;
use App\Interface\UseCase\Modules\Products\Categories\EditCategoriesInterface;
use App\Interface\UseCase\Modules\Products\Categories\ListCategoriesInterface;
use App\Interface\UseCase\Modules\Products\Categories\ToggleStatusCategoriesInterface;
use App\Repository\Configurations\Cities\CitiesCategoriesRepository;
use App\Repository\Configurations\Countries\CountriesCategoriesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Regions\RegionsCategoriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '{_locale<%supported_locales%>}/dashboard/products/categories', name: 'dashboard_products_categories')]
class CategoriesController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('dashboard/modules/products/categories/list.html.twig');
    }

    #[Route('/new', name: '_new', methods: ['GET', 'POST'])]
    public function new(
        AddCategoriesInterface $addCategories,
        CountriesRepository $countriesRepository,
    ): Response {
        $process = $addCategories->handler();
        if ($process->isProcess()) {
            $type = $process->getFlashType();
            $this->addFlash($type, $process->getMessage());
        }
        return $this->render('dashboard/modules/products/categories/categorie.html.twig', [
            'form'               => $process->getForm(),
            'allCountries'       => $countriesRepository->findAllActive(),
            'selectedCountryIds' => '[]',
            'selectedRegionIds'  => '[]',
            'selectedCityIds'    => '[]',
        ]);
    }

    #[Route('/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(
        Categories $categorie,
        EditCategoriesInterface $editCategories,
        CountriesRepository $countriesRepository,
        CountriesCategoriesRepository $countriesCategoriesRepository,
        RegionsCategoriesRepository $regionsCategoriesRepository,
        CitiesCategoriesRepository $citiesCategoriesRepository,
    ): Response {
        $process = $editCategories->handler($categorie);
        if ($process->isProcess()) {
            $type = $process->getFlashType();
            $this->addFlash($type, $process->getMessage());
        }
        return $this->render('dashboard/modules/products/categories/categorie.html.twig', [
            'form'               => $process->getForm(),
            'isEdit'             => true,
            'image'              => $categorie->getImage(),
            'allCountries'       => $countriesRepository->findAllActive(),
            'selectedCountryIds' => json_encode($countriesCategoriesRepository->getSelectedIds($categorie)),
            'selectedRegionIds'  => json_encode($regionsCategoriesRepository->getSelectedIds($categorie)),
            'selectedCityIds'    => json_encode($citiesCategoriesRepository->getSelectedIds($categorie)),
        ]);
    }

    #[Route('/{id}/toggle-status', name: '_toggle_status', methods: ['POST'])]
    public function toggleStatus(Categories $categorie, ToggleStatusCategoriesInterface $toggleStatus): Response
    {
        return $this->json($toggleStatus->handler($categorie));
    }

    #[Route('/list', name: '_list', methods: ['POST', 'GET'])]
    public function listCategories(ListCategoriesInterface $listCategories): Response
    {
        return $this->json($listCategories->handler(), 200);
    }
}
