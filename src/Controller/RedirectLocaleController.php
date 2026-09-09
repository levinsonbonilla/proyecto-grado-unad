<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class RedirectLocaleController
{
    #[Route(
        path: '/{path}',
        name: 'redirect_to_locale',
        requirements: ['path' => '^(?!es|en|br|checkout/webhook).*']
    )]
    public function redirectToLocale(Request $request, string $path): RedirectResponse
    {
        $locales = ['es', 'en', 'br'];
        $firstSegment = explode('/', $path)[0];
        if (!in_array($firstSegment, haystack: $locales)) {
            $lang = $request->getSession()->get('_locale', "es");
            return new RedirectResponse("/$lang/$path", 301);
        }

        return new RedirectResponse("/$path", 301);
    }
}
