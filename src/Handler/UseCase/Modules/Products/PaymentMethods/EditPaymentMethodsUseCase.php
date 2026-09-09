<?php

namespace App\Handler\UseCase\Modules\Products\PaymentMethods;

use App\Entity\Configurations\Cities\CitiesPaymentMethods;
use App\Entity\Configurations\Countries\CountriesPaymentMethods;
use App\Entity\Configurations\Regions\RegionsPaymentMethods;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Form\Modules\Products\PaymentMethodsType;
use App\Handler\Shared\AbstractEditHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Modules\Products\PaymentMethods\EditPaymentMethodsInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Configurations\Cities\CitiesPaymentMethodsRepository;
use App\Repository\Configurations\Countries\CountriesPaymentMethodsRepository;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Repository\Configurations\Regions\RegionsPaymentMethodsRepository;
use App\Repository\Tenants\Domains\PaymentMethodsRepository;
use App\ReturnHandler\FormReturn;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EditPaymentMethodsUseCase extends AbstractEditHandler implements EditPaymentMethodsInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly PaymentMethodsRepository $paymentMethodsRepository,
        private readonly CountriesPaymentMethodsRepository $countriesPaymentMethodsRepository,
        private readonly RegionsPaymentMethodsRepository $regionsPaymentMethodsRepository,
        private readonly CitiesPaymentMethodsRepository $citiesPaymentMethodsRepository,
        private readonly CountriesRepository $countriesRepository,
        private readonly RegionsRepository $regionsRepository,
        private readonly CitiesRepository $citiesRepository,
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(PaymentMethods $entity): FormReturn
    {
        return $this->process($entity);
    }

    protected function formType(): string
    {
        return PaymentMethodsType::class;
    }

    protected function formName(): string
    {
        return 'payment_methods';
    }

    protected function assembleForm(FormInterface $form, object $entity): FormInterface
    {

        $form->get('name')->setData($entity->getName());
        $form->get('provider')->setData($entity->getProvider());
        $form->get('instructions')->setData($entity->getInstructions());
        return $form;
    }

    protected function edit(array $data, object $entity): void
    {

        $entity->edit($data['name'], $data['provider'] ?? PaymentMethods::PROVIDER_MANUAL, $data['instructions'] ?? null);

        $this->syncCountries($entity, array_filter((array)($data['countries'] ?? [])));
        $this->syncRegions($entity, array_filter((array)($data['regions'] ?? [])));
        $this->syncCities($entity, array_filter((array)($data['cities'] ?? [])));
        $this->customeEntityManager->flush();

        $this->countriesPaymentMethodsRepository->invalidateSelectedIdsCache($entity);
        $this->regionsPaymentMethodsRepository->invalidateSelectedIdsCache($entity);
        $this->citiesPaymentMethodsRepository->invalidateSelectedIdsCache($entity);

        $this->paymentMethodsRepository->invalidateCache($entity->getId());
        $this->customeEntityManager->add($entity, true);
    }

    private function syncCountries(PaymentMethods $entity, array $incomingIds): void
    {
        $existing = $this->countriesPaymentMethodsRepository->findAllByPaymentMethod($entity);
        $existingById = [];
        foreach ($existing as $link) {
            $existingById[(string)$link->getCountry()->getId()] = $link;
        }
        foreach ($incomingIds as $countryId) {
            if (isset($existingById[$countryId])) {
                $existingById[$countryId]->activate();
            } else {
                $country = $this->countriesRepository->find($countryId);
                if ($country) {
                    $this->customeEntityManager->add((new CountriesPaymentMethods())->add($country, $entity), false);
                }
            }
        }
        foreach ($existingById as $id => $link) {
            if (!in_array($id, $incomingIds, true)) {
                $link->deactivate();
                $this->customeEntityManager->add($link, false);
            }
        }
    }

    private function syncRegions(PaymentMethods $entity, array $incomingIds): void
    {
        $existing = $this->regionsPaymentMethodsRepository->findAllByPaymentMethod($entity);
        $existingById = [];
        foreach ($existing as $link) {
            $existingById[(string)$link->getRegion()->getId()] = $link;
        }
        foreach ($incomingIds as $regionId) {
            if (isset($existingById[$regionId])) {
                $existingById[$regionId]->activate();
            } else {
                $region = $this->regionsRepository->find($regionId);
                if ($region) {
                    $this->customeEntityManager->add((new RegionsPaymentMethods())->add($region, $entity), false);
                }
            }
        }
        foreach ($existingById as $id => $link) {
            if (!in_array($id, $incomingIds, true)) {
                $link->deactivate();
                $this->customeEntityManager->add($link, false);
            }
        }
    }

    private function syncCities(PaymentMethods $entity, array $incomingIds): void
    {
        $existing = $this->citiesPaymentMethodsRepository->findAllByPaymentMethod($entity);
        $existingById = [];
        foreach ($existing as $link) {
            $existingById[(string)$link->getCity()->getId()] = $link;
        }
        foreach ($incomingIds as $cityId) {
            if (isset($existingById[$cityId])) {
                $existingById[$cityId]->activate();
            } else {
                $city = $this->citiesRepository->find($cityId);
                if ($city) {
                    $this->customeEntityManager->add((new CitiesPaymentMethods())->add($city, $entity), false);
                }
            }
        }
        foreach ($existingById as $id => $link) {
            if (!in_array($id, $incomingIds, true)) {
                $link->deactivate();
                $this->customeEntityManager->add($link, false);
            }
        }
    }
}
