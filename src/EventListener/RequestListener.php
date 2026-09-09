<?php

namespace App\EventListener;

use App\Interface\Configuration\LocationInterface;
use App\Util\StringUtil;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RequestListener implements EventSubscriberInterface
{

    public function __construct(
        private readonly LocationInterface $location
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest()) {
            return;
        }

        if ($request->attributes->get('_route') === 'store_checkout_webhook_payment') {
            return;
        }

        $localeFromUrl = $request->attributes->get('_locale');

        if ($locale = $request->get('lang')) {

            $request->getSession()->set('_locale', $locale);
        } elseif ($localeFromUrl !== null) {

            $request->getSession()->set('_locale', $localeFromUrl);
            $request->getSession()->set('change_language', $localeFromUrl);
        } elseif (!$request->getSession()->get("change_language", false)) {

            $newLocale = $this->location->getLanguage();
            $request->getSession()->set('_locale', $newLocale);
            $request->getSession()->set('change_language', $newLocale);

            try {
                $response = new RedirectResponse(StringUtil::changeUrlWithLang($request->getRequestUri(), $newLocale));
            } catch (\Throwable $th) {
                $response = new RedirectResponse($newLocale.$request->getRequestUri());
            }
            $event->setResponse($response);
            return;
        } else {

            $localeFromSession = $request->getSession()->get('_locale', "en");
            $request->setLocale($localeFromSession);
            $request->getSession()->set('_locale', $localeFromSession);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 17]],
        ];
    }
}
