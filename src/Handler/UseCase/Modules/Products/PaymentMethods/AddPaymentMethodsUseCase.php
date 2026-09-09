<?php

namespace App\Handler\UseCase\Modules\Products\PaymentMethods;

use App\Entity\Configurations\Cities\CitiesPaymentMethods;
use App\Entity\Configurations\Countries\CountriesPaymentMethods;
use App\Entity\Configurations\Regions\RegionsPaymentMethods;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Handler\Configuration\GetDomainData;
use App\Handler\Shared\AbstractAddHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Modules\Products\PaymentMethods\AddPaymentMethodsInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\ReturnHandler\FormReturn;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AddPaymentMethodsUseCase extends AbstractAddHandler implements AddPaymentMethodsInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly GetDomainData $getDomainData,
        private readonly CountriesRepository $countriesRepository,
        private readonly RegionsRepository $regionsRepository,
        private readonly CitiesRepository $citiesRepository,
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(string $type, ?array $additionalData = null): FormReturn
    {
        return $this->process($type, $additionalData);
    }

    protected function add(array $data, array $additionalData): void
    {
        $domain = $this->getDomainData->getDomain();
        $entity = new PaymentMethods();
        $entity->add($domain, $data['name'], $data['provider'] ?? PaymentMethods::PROVIDER_MANUAL, $data['instructions'] ?? null);
        $this->customeEntityManager->add($entity, true);

        foreach (array_filter((array)($data['countries'] ?? [])) as $id) {
            $country = $this->countriesRepository->find($id);
            if ($country) {
                $this->customeEntityManager->add((new CountriesPaymentMethods())->add($country, $entity), false);
            }
        }
        foreach (array_filter((array)($data['regions'] ?? [])) as $id) {
            $region = $this->regionsRepository->find($id);
            if ($region) {
                $this->customeEntityManager->add((new RegionsPaymentMethods())->add($region, $entity), false);
            }
        }
        foreach (array_filter((array)($data['cities'] ?? [])) as $id) {
            $city = $this->citiesRepository->find($id);
            if ($city) {
                $this->customeEntityManager->add((new CitiesPaymentMethods())->add($city, $entity), false);
            }
        }

        $this->customeEntityManager->flush();
    }
}
