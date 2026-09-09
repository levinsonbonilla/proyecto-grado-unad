<?php

namespace App\Controller\Store;

use App\Interface\UseCase\Store\Checkout\PrepareCheckoutInterface;
use App\Interface\UseCase\Store\Profile\EditUserProfileInterface;
use App\Interface\UseCase\Store\Profile\GetUserProfileInterface;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Service\Geo\GeoCascadeResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route(path: '{_locale<%supported_locales%>}/profile', name: 'store_profile')]
class ProfileController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(
        Request $request,
        GetUserProfileInterface $getProfile,
        CountriesRepository $countriesRepository,
    ): Response {
        $countries = array_map(fn($c) => [
            'id'   => (string) $c->getId(),
            'name' => $c->getName($request->getLocale()),
        ], $countriesRepository->findAllActive());

        return $this->render('e_commerce/theme_1/profile/profile.html.twig', [
            'profile'   => $getProfile->handler(),
            'countries' => $countries,
        ]);
    }

    #[Route('/edit', name: '_edit', methods: ['POST'])]
    public function edit(Request $request, EditUserProfileInterface $editProfile): Response
    {
        $data   = json_decode($request->getContent(), true) ?? $request->request->all();
        $result = $editProfile->handler($data);

        if ($request->getContentTypeFormat() === 'json' || str_contains($request->headers->get('Accept', ''), 'application/json')) {
            return $this->json($result);
        }

        $this->addFlash($result['success'] ? 'success' : 'error', $result['message'] ?? '');
        return $this->redirectToRoute('store_profile', ['_locale' => $this->getParameter('locale')]);
    }

    #[Route('/api/regions', name: '_api_regions', methods: ['GET'])]
    public function apiRegions(Request $request, GeoCascadeResolverInterface $geoCascade): JsonResponse
    {
        $countryId = $request->query->get('countryId', '');
        return $this->json($geoCascade->regionsForCountries([$countryId], $request->getLocale()));
    }

    #[Route('/api/cities', name: '_api_cities', methods: ['GET'])]
    public function apiCities(Request $request, GeoCascadeResolverInterface $geoCascade): JsonResponse
    {
        $regionId = $request->query->get('regionId', '');
        return $this->json($geoCascade->citiesForRegions([$regionId], $request->getLocale()));
    }
}
