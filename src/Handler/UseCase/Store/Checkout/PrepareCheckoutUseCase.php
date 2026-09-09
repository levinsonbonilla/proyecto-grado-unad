<?php

namespace App\Handler\UseCase\Store\Checkout;

use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Cart\GetCartInterface;
use App\Interface\UseCase\Store\Checkout\PrepareCheckoutInterface;
use App\Repository\Configurations\Countries\CountriesDomainsRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Service\Geo\GeoLocation;
use App\Service\Payments\PaymentMethodAvailabilityResolverInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class PrepareCheckoutUseCase implements PrepareCheckoutInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly GetCartInterface $getCart,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly CountriesRepository $countriesRepository,
        private readonly CountriesDomainsRepository $countriesDomainsRepository,
        private readonly PaymentMethodAvailabilityResolverInterface $paymentMethodAvailability,
        private readonly RequestStack $requestStack,
        private readonly LogInterface $log,
    ) {}

    public function handler(): array
    {
        try {
            $cart = $this->getCart->handler();
            if (empty($cart['items'])) {
                return ['redirect' => 'cart'];
            }

            $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'es';
            $user   = $this->security->getUser();
            $domain = $this->getDomainData->getDomainCache();

            $allCountries = $this->countriesRepository->findAllActive();

            $domainCountryIds = $this->countriesDomainsRepository->getSelectedIds($domain);
            $countries = empty($domainCountryIds)
                ? $allCountries
                : array_values(array_filter(
                    $allCountries,
                    fn ($c) => in_array((string) $c->getId(), $domainCountryIds, true)
                ));

            $userCountry = $user?->getCountry();
            $userRegion  = $user?->getRegion();
            $userCity    = $user?->getCity();

            $countriesList = array_map(fn($c) => [
                'id'   => (string) $c->getId(),
                'name' => $c->getName($locale),
            ], $countries);

            $geo = new GeoLocation($userCountry, $userRegion, $userCity);
            $paymentMethods = $this->paymentMethodAvailability->resolve($domain, $geo);

            $paymentMethodsList = array_map(fn($pm) => [
                'id'           => (string) $pm->getId(),
                'name'         => $pm->getName(),
                'provider'     => $pm->getProvider(),
                'instructions' => $pm->getInstructions(),
            ], $paymentMethods);

            return [
                'cart'           => $cart,
                'countries'      => $countriesList,
                'paymentMethods' => $paymentMethodsList,
                'prefill' => [
                    'address'         => $user?->getAddress() ?? '',
                    'countryId'       => $userCountry ? (string) $userCountry->getId() : null,
                    'countryName'     => $userCountry?->getName($locale) ?? '',
                    'regionId'        => $userRegion ? (string) $userRegion->getId() : null,
                    'regionName'      => $userRegion?->getName($locale) ?? '',
                    'cityId'          => $userCity ? (string) $userCity->getId() : null,
                    'cityName'        => $userCity?->getName($locale) ?? '',
                ],
            ];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['error' => true];
        }
    }
}
