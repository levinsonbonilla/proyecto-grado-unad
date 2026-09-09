<?php

namespace App\EventListener;

use App\Entity\Users\Users;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\LocationInterface;
use App\Message\Statistics\RecordStatisticsMessage;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\MessageBusInterface;

class StatisticsResponseListener implements EventSubscriberInterface
{
    private const PUBLIC_ROUTE_PREFIXES = ['store', 'public', 'general_products'];

    private const EXCLUDED_ROUTES = ['public_lang'];

    public function __construct(
        private readonly MessageBusInterface    $bus,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly LocationInterface      $location,
        private readonly Security               $security,
    ) {}

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        if ($response->getStatusCode() >= 400) {
            return;
        }

        $request   = $event->getRequest();
        $routeName = $request->attributes->get('_route', '');

        if (!$this->isPublicRoute($routeName)) {
            return;
        }

        $path = $request->getPathInfo();
        if (preg_match('#\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf)$#i', $path)) {
            return;
        }

        try {
            $domain = $this->getDomainData->getDomainCache();
        } catch (\Throwable) {
            return;
        }

        $locale  = $request->getLocale();
        $country = $this->location->getCountry();
        $region  = $this->location->getRegion();
        $city    = $this->location->getCity();
        $user    = $this->security->getUser();

        $this->bus->dispatch(new RecordStatisticsMessage(
            domainId:    (string) $domain->getId(),
            ip:          $request->getClientIp(),
            userAgent:   $request->headers->get('User-Agent'),
            lang:        $locale,
            page:        $path,
            referrer:    $request->headers->get('referer'),
            utmSource:   $request->query->get('utm_source'),
            utmMedium:   $request->query->get('utm_medium'),
            utmCampaign: $request->query->get('utm_campaign'),
            sessionId:   $request->hasSession() ? $request->getSession()->getId() : null,
            country:        $country?->getName($locale),
            region:         $region?->getName($locale),
            city:           $city?->getName($locale),
            countryIsoCode: $country?->getIsoCode(),
            userId:         $user instanceof Users ? (string) $user->getId() : null,
        ));
    }

    private function isPublicRoute(string $routeName): bool
    {
        if (in_array($routeName, self::EXCLUDED_ROUTES, true)) {
            return false;
        }
        foreach (self::PUBLIC_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }
        return false;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }
}
