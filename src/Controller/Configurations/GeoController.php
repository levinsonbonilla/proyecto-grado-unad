<?php

namespace App\Controller\Configurations;

use App\Service\Geo\GeoCascadeResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '{_locale<%supported_locales%>}/dashboard/geo', name: 'dashboard_geo')]
final class GeoController extends AbstractController
{
    #[Route('/regions', name: '_regions', methods: ['GET'])]
    public function regions(Request $request, GeoCascadeResolverInterface $geoCascade): JsonResponse
    {
        $countryIds = array_filter((array)$request->query->all('country'));

        return $this->json($geoCascade->regionsForCountries($countryIds, $request->getLocale()));
    }

    #[Route('/cities', name: '_cities', methods: ['GET'])]
    public function cities(Request $request, GeoCascadeResolverInterface $geoCascade): JsonResponse
    {
        $regionIds = array_filter((array)$request->query->all('region'));

        return $this->json($geoCascade->citiesForRegions($regionIds, $request->getLocale()));
    }
}
