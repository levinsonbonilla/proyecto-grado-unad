<?php

namespace App\Controller\Dashboard;

use App\Entity\Tenants\Tenants;
use App\Interface\UseCase\Dashboard\Domains\AddDomainInterface;
use App\Interface\UseCase\Dashboard\Tenants\AddTenantInterface;
use App\Interface\UseCase\Dashboard\Tenants\EditTenantInterface;
use App\Interface\UseCase\Dashboard\Tenants\ListTenantsInterface;
use App\Interface\UseCase\Dashboard\Tenants\ToggleStatusTenantInterface;
use App\Repository\Configurations\Globals\CountriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
#[Route(path: '{_locale<%supported_locales%>}/dashboard/tenants', name: 'dashboard_tenants')]
class TenantsController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('dashboard/tenants/list.html.twig');
    }

    #[Route('/new', name: '_new', methods: ['GET', 'POST'])]
    public function new(AddTenantInterface $addTenant): Response
    {
        $process = $addTenant->handler();
        if ($process->isProcess()) {
            $type = $process->getFlashType();
            $this->addFlash($type, $process->getMessage());
        }
        return $this->render('dashboard/tenants/tenant.html.twig', [
            'form' => $process->getForm()
        ]);
    }

    #[Route('/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Tenants $tenant, EditTenantInterface $editUser): Response
    {
        $process = $editUser->handler($tenant);
        if ($process->isProcess()) {
            $type = $process->getFlashType();
            $this->addFlash($type, $process->getMessage());
        }
        return $this->render('dashboard/tenants/tenant.html.twig', [
            'form' => $process->getForm(),
            'isEdit' => true
        ]);
    }

    #[Route('/{id}/toggle-status', name: '_toggle_status', methods: ['POST'])]
    public function toggleStatus(Tenants $tenant, ToggleStatusTenantInterface $toggleStatus): Response
    {
        return $this->json($toggleStatus->handler($tenant));
    }

    #[Route('/{id}/domains/new', name: '_domains_new', methods: ['GET', 'POST'])]
    public function newDomain(
        Tenants $tenant,
        AddDomainInterface $addDomain,
        CountriesRepository $countriesRepository,
    ): Response {
        $process = $addDomain->handler($tenant);
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

    #[Route('/list', name: '_list', methods: ['POST', 'GET'])]
    public function listTenants(ListTenantsInterface $listTenants): Response
    {
        return $this->json($listTenants->handler(), 200);
    }
}
