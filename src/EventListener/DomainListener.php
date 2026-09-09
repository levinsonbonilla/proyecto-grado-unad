<?php

namespace App\EventListener;

use App\Exception\GenericException;
use App\Interface\Configuration\GetDomainDataInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class DomainListener
{
    public function __construct(
        private readonly GetDomainDataInterface $getDomainData,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        try {

            $this->getDomainData->getDomain();
        } catch (GenericException $ge) {
            $content = $this->twig->render('public/forbidden.html.twig');
            $event->setResponse(new Response($content, Response::HTTP_FORBIDDEN));
        } catch (\Throwable $th) {
            $content = $this->twig->render('public/forbidden.html.twig');
            $event->setResponse(new Response($content, Response::HTTP_FORBIDDEN));
        }
    }
}
