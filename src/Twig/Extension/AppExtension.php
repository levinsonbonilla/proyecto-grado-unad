<?php

namespace App\Twig\Extension;

use App\Entity\Tenants\Domains\Domains;
use App\Interface\Configuration\ActiveDashboardDomainResolverInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Store\Products\GetPublicCategoriesInterface;
use App\Interface\Service\Currency\CurrentCurrencyResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\Extra\Intl\IntlExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly GetDomainDataInterface $getDomainData,
        private readonly RequestStack $request,
        private readonly ActiveDashboardDomainResolverInterface $activeDomainResolver,
        private readonly GetPublicCategoriesInterface $publicCategories,
        private readonly CurrentCurrencyResolverInterface $currencyResolver,
    ) {
    }

    #[\Override]
    public function getFilters(): array
    {
        return [

            new TwigFilter('currentTenant', $this->currentTenant(...)),
            new TwigFilter('isCurrentRoute', $this->getCurrentRoute(...)),
            new TwigFilter('price', $this->formatPrice(...)),
        ];
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [

            new TwigFunction('dashboard_active_domain', $this->dashboardActiveDomain(...)),
            new TwigFunction('dashboard_selectable_domains', $this->dashboardSelectableDomains(...)),
            new TwigFunction('store_current_domain', $this->storeCurrentDomain(...)),
            new TwigFunction('store_footer_categories', $this->storeFooterCategories(...)),
            new TwigFunction('currentCurrency', $this->getCurrentCurrency(...)),
        ];
    }

    public function dashboardActiveDomain(): ?Domains
    {
        $request = $this->request->getCurrentRequest();
        if ($request === null) {
            return null;
        }
        return $this->activeDomainResolver->resolve($request);
    }

    public function dashboardSelectableDomains(): array
    {
        return $this->activeDomainResolver->getSelectableDomains();
    }

    public function storeCurrentDomain(): ?Domains
    {
        try {
            return $this->getDomainData->getDomainCache();
        } catch (\Throwable) {
            return null;
        }
    }

    public function storeFooterCategories(): array
    {
        return $this->publicCategories->handler();
    }

    private function currentTenant(string $value): string
    {
        $return = match ($value) {
            'name' => ucfirst($this->getDomainData->getTenantCache()->getName()),
            'supportEmail' => $this->getDomainData->getDomainCache()->getSupportEmail(),
            default => "",
        };
        return $return;
    }

    public function formatPrice(int|float|string|null $amount): string
    {
        $currency = $this->currencyResolver->resolve($this->getDomainData->getDomainCache());

        if ($amount === null) {
            return $currency->symbol . '0';
        }

        $divisor = 10 ** $currency->decimalPlaces;

        return $currency->symbol . number_format(
            ((int) $amount) / $divisor,
            $currency->decimalPlaces,
            $currency->decimalSeparator,
            $currency->thousandsSeparator
        );
    }

    public function getCurrentCurrency(): array
    {
        $currency = $this->currencyResolver->resolve($this->getDomainData->getDomainCache());

        return [
            'code' => $currency->code,
            'symbol' => $currency->symbol,
            'decimalPlaces' => $currency->decimalPlaces,
            'thousandsSeparator' => $currency->thousandsSeparator,
            'decimalSeparator' => $currency->decimalSeparator,
        ];
    }

    public function getCurrentRoute(string $value): bool
    {
        $request = $this->request->getCurrentRequest();
        if (!$request) {
            return false;
        }

        $route = $request->attributes->get('_route');
        if (!$route) {
            return false;
        }

        $alias = explode(',', $value);
        foreach ($alias as $key => $alia) {
            if (strpos($route, $alia)) {
                return true;
            }
        }

        return false;
    }
}
