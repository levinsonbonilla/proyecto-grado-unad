<?php

namespace App\Service\Payments;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Repository\Configurations\Cities\CitiesPaymentMethodsRepository;
use App\Repository\Configurations\Countries\CountriesPaymentMethodsRepository;
use App\Repository\Configurations\Regions\RegionsPaymentMethodsRepository;
use App\Repository\Tenants\Domains\PaymentMethodsRepository;
use App\Service\Geo\GeoLocation;
use App\Util\GeoMatchUtil;

final readonly class PaymentMethodAvailabilityResolverService implements PaymentMethodAvailabilityResolverInterface
{
    public function __construct(
        private PaymentMethodsRepository $paymentMethodsRepository,
        private CountriesPaymentMethodsRepository $countriesPaymentMethodsRepository,
        private RegionsPaymentMethodsRepository $regionsPaymentMethodsRepository,
        private CitiesPaymentMethodsRepository $citiesPaymentMethodsRepository,
    ) {
    }

    public function resolve(Domains $domain, GeoLocation $geo): array
    {
        $paymentMethods = $this->paymentMethodsRepository->findBy(['domain' => $domain, 'active' => true]);

        return array_values(array_filter(
            $paymentMethods,
            fn (PaymentMethods $paymentMethod): bool => $this->isAvailable($paymentMethod, $geo)
        ));
    }

    public function isAvailable(PaymentMethods $paymentMethod, GeoLocation $geo): bool
    {
        $countryIds = $this->countriesPaymentMethodsRepository->getSelectedIds($paymentMethod);
        $regionIds  = $this->regionsPaymentMethodsRepository->getSelectedIds($paymentMethod);
        $cityIds    = $this->citiesPaymentMethodsRepository->getSelectedIds($paymentMethod);

        return GeoMatchUtil::matches($countryIds, $regionIds, $cityIds, $geo);
    }
}
