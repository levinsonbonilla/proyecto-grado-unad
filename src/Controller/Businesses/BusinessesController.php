<?php

namespace App\Controller\Businesses;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '{_locale<%supported_locales%>}/dashboard/businesses', name: 'dashboard_businesses')]
final class BusinessesController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->redirectToRoute('dashboard_businesses_modules', ['_locale' => $request->getLocale()]);
    }
}
