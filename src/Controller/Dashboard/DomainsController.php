<?php

namespace App\Controller\Dashboard;

use App\Entity\Tenants\Domains\Domains;
use App\Interface\UseCase\Dashboard\Domains\AddDomainInterface;
use App\Interface\UseCase\Dashboard\Domains\EditDomainInterface;
use App\Interface\UseCase\Dashboard\Domains\ListDomainsInterface;
use App\Interface\UseCase\Dashboard\Domains\ToggleStatusDomainInterface;
use App\Repository\Configurations\Cities\CitiesDomainsRepository;
use App\Repository\Configurations\Countries\CountriesDomainsRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Regions\RegionsDomainsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '{_locale<%supported_locales%>}/dashboard/configurations/domains', name: 'dashboard_configurations_domains')]
final class DomainsController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('dashboard/domains/list.html.twig');
    }

    #[Route('/new', name: '_new', methods: ['GET', 'POST'])]
    public function new(
        AddDomainInterface $addDomain,
        CountriesRepository $countriesRepository,
    ): Response {
        $process = $addDomain->handler();
        if ($process->isProcess()) {
            $type = $process->getFlashType();
            $this->addFlash($type, $process->getMessage());
        }
        return $this->render('dashboard/domains/domain.html.twig', [
            'form'               => $process->getForm(),
            'allCountries'       => $countriesRepository->findAllActive(),
            'selectedCountryIds' => '[]',
            'selectedRegionIds'  => '[]',
            'selectedCityIds'    => '[]',
        ]);
    }

    #[Route('/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(
        Domains $domain,
        EditDomainInterface $editDomain,
        CountriesRepository $countriesRepository,
        CountriesDomainsRepository $countriesDomainsRepository,
        RegionsDomainsRepository $regionsDomainsRepository,
        CitiesDomainsRepository $citiesDomainsRepository,
    ): Response {
        $process = $editDomain->handler($domain);
        if ($process->isProcess()) {
            $type = $process->getFlashType();
            $this->addFlash($type, $process->getMessage());
        }
        return $this->render('dashboard/domains/domain.html.twig', [
            'form'               => $process->getForm(),
            'isEdit'             => true,
            'domain'             => $domain,
            'allCountries'       => $countriesRepository->findAllActive(),
            'selectedCountryIds' => json_encode($countriesDomainsRepository->getSelectedIds($domain)),
            'selectedRegionIds'  => json_encode($regionsDomainsRepository->getSelectedIds($domain)),
            'selectedCityIds'    => json_encode($citiesDomainsRepository->getSelectedIds($domain)),
        ]);
    }

    #[Route('/{id}/toggle-status', name: '_toggle_status', methods: ['POST'])]
    public function toggleStatus(Domains $domain, ToggleStatusDomainInterface $toggleStatus): Response
    {
        return $this->json($toggleStatus->handler($domain));
    }

    #[Route('/list', name: '_list', methods: ['POST', 'GET'])]
    public function listDomains(ListDomainsInterface $list): Response
    {
        return $this->json($list->handler(), 200);
    }
}
