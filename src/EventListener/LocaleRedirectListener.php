<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;

class LocaleRedirectListener
{
    private $router;
    private $defaultLocale;

    public function __construct(RouterInterface $router, string $defaultLocale = 'es')
    {
        $this->router = $router;
        $this->defaultLocale = $defaultLocale;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();

        if (!preg_match('#^/(es|en|br)#', $pathInfo)) {
            $locale = $this->defaultLocale;
            $newUrl = "/$locale$pathInfo";

            $event->setResponse(new RedirectResponse($newUrl));
        }
    }
}
