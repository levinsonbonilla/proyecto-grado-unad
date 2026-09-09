<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class RememberMeWitnessCookieListener
{
    public function __construct(
        private readonly string $sessionCookieDomain,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || $this->sessionCookieDomain !== '') {
            return;
        }

        $rememberMeCookie = null;
        foreach ($event->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === RememberMeCookieGateListener::REMEMBER_ME_COOKIE_NAME) {
                $rememberMeCookie = $cookie;
                break;
            }
        }
        if ($rememberMeCookie === null) {
            return;
        }

        $witnessName = RememberMeCookieGateListener::witnessCookieName($event->getRequest()->getHttpHost());
        $isDeleting = $rememberMeCookie->isCleared();

        $event->getResponse()->headers->setCookie(new Cookie(
            $witnessName,
            $isDeleting ? null : '1',
            $isDeleting ? 1 : $rememberMeCookie->getExpiresTime(),
            '/',
            null,
            $rememberMeCookie->isSecure(),
            true,
            false,
            'lax'
        ));
    }
}
