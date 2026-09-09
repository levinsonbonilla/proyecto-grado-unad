<?php

namespace App\Controller\Modules\Products;

use App\Entity\Tenants\Domains\PaymentMethods;
use App\Form\Modules\Products\PaymentMethodsType;
use App\Interface\UseCase\Modules\Products\PaymentMethods\AddPaymentMethodsInterface;
use App\Interface\UseCase\Modules\Products\PaymentMethods\DeletePaymentMethodsInterface;
use App\Interface\UseCase\Modules\Products\PaymentMethods\EditPaymentMethodsInterface;
use App\Interface\UseCase\Modules\Products\PaymentMethods\ListPaymentMethodsInterface;
use App\Repository\Configurations\Cities\CitiesPaymentMethodsRepository;
use App\Repository\Configurations\Countries\CountriesPaymentMethodsRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Regions\RegionsPaymentMethodsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '{_locale<%supported_locales%>}/dashboard/products/payment-methods', name: 'dashboard_products_payment_methods')]
final class PaymentMethodsController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('dashboard/modules/products/payment_methods/list.html.twig');
    }

    #[Route('/new', name: '_new', methods: ['GET', 'POST'])]
    public function new(
        AddPaymentMethodsInterface $addPaymentMethods,
        CountriesRepository $countriesRepository,
    ): Response {
        $process = $addPaymentMethods->handler(PaymentMethodsType::class);
        if ($process->isProcess()) {
            $type = $process->getFlashType();
            $this->addFlash($type, $process->getMessage());
        }
        return $this->render('dashboard/modules/products/payment_methods/payment_method.html.twig', [
            'form'               => $process->getForm(),
            'allCountries'       => $countriesRepository->findAllActive(),
            'selectedCountryIds' => '[]',
            'selectedRegionIds'  => '[]',
            'selectedCityIds'    => '[]',
        ]);
    }

    #[Route('/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(
        PaymentMethods $entity,
        EditPaymentMethodsInterface $editPaymentMethods,
        CountriesRepository $countriesRepository,
        CountriesPaymentMethodsRepository $countriesPaymentMethodsRepository,
        RegionsPaymentMethodsRepository $regionsPaymentMethodsRepository,
        CitiesPaymentMethodsRepository $citiesPaymentMethodsRepository,
    ): Response {
        $process = $editPaymentMethods->handler($entity);
        if ($process->isProcess()) {
            $type = $process->getFlashType();
            $this->addFlash($type, $process->getMessage());
        }
        return $this->render('dashboard/modules/products/payment_methods/payment_method.html.twig', [
            'form'               => $process->getForm(),
            'isEdit'             => true,
            'entity'             => $entity,
            'allCountries'       => $countriesRepository->findAllActive(),
            'selectedCountryIds' => json_encode($countriesPaymentMethodsRepository->getSelectedIds($entity)),
            'selectedRegionIds'  => json_encode($regionsPaymentMethodsRepository->getSelectedIds($entity)),
            'selectedCityIds'    => json_encode($citiesPaymentMethodsRepository->getSelectedIds($entity)),
        ]);
    }

    #[Route('/{id}/delete', name: '_delete', methods: ['POST'])]
    public function delete(PaymentMethods $entity, DeletePaymentMethodsInterface $deletePaymentMethods): Response
    {
        return $this->json($deletePaymentMethods->handler($entity));
    }

    #[Route('/list', name: '_list', methods: ['POST', 'GET'])]
    public function listItems(ListPaymentMethodsInterface $list): Response
    {
        return $this->json($list->handler(), 200);
    }
}
