<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;

class RememberMeCookieGateListener
{
    public const REMEMBER_ME_COOKIE_NAME = 'REMEMBERME';
    public const WITNESS_COOKIE_PREFIX = 'THRMBRHOST';

    public function __construct(
        private readonly string $sessionCookieDomain,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $this->sessionCookieDomain !== '') {
            return;
        }

        $request = $event->getRequest();
        if (!$request->cookies->has(self::REMEMBER_ME_COOKIE_NAME)) {
            return;
        }

        $witnessName = self::witnessCookieName($request->getHttpHost());
        if ($request->cookies->get($witnessName) !== '1') {
            $request->cookies->remove(self::REMEMBER_ME_COOKIE_NAME);
        }
    }

    public static function witnessCookieName(string $host): string
    {
        return self::WITNESS_COOKIE_PREFIX . strtoupper(substr(hash('crc32b', $host), 0, 8));
    }
}
