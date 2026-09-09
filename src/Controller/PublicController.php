<?php

namespace App\Controller;

use App\Interface\Configuration\GetDomainDataInterface;
use App\Util\StringUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\LocaleAwareInterface;

#[Route(path: '{_locale<%supported_locales%>}/public/', name: 'public')]
class PublicController extends AbstractController
{

    public function __construct(
        private readonly LocaleAwareInterface $translator,
        private readonly GetDomainDataInterface $getDomainData
    ) {
    }

    #[Route(path: 'forbidden', name: '_forbidden')]
    public function forbidden(
    ): Response {
        return $this->render('public/forbidden.html.twig');
    }

    #[Route(path: 'internal/server/error', name: '_internal_server_error')]
    public function errorPage(
    ): Response {
        return $this->render('public/forbidden.html.twig');
    }

    #[Route(path: 'not/found', name: 'not_found')]
    public function notFound(
    ): Response {
        return $this->render('public/not_found.html.twig');
    }

    #[Route(path: 'privacy', name: '_privacy')]
    public function privacy(
    ): Response {
        return $this->render('public/not_found.html.twig');
    }

    #[Route(path: 'terms', name: '_terms')]
    public function terms(
    ): Response {
        return $this->render('public/not_found.html.twig');
    }

    #[Route(path: 'lang/{lang}', name: '_lang')]
    public function lang(Request $request, string $lang): Response
    {
        $referer = $request->headers->get('referer');
        $url = $referer ? $referer : $this->generateUrl('security_login');

        return $this->redirect(StringUtil::changeUrlWithLang($url, $lang));
    }

}
