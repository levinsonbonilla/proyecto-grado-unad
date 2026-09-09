<?php

namespace App\Controller\Store;

use App\Entity\Products\Orders\Orders;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Store\Checkout\ConfirmOrderInterface;
use App\Interface\UseCase\Store\Checkout\PrepareCheckoutInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Service\Geo\GeoCascadeResolverInterface;
use App\Service\Geo\GeoLocation;
use App\Service\Payments\PaymentMethodAvailabilityResolverInterface;
use App\Util\StringUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route(path: '{_locale<%supported_locales%>}/checkout', name: 'store_checkout')]
class CheckoutController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(PrepareCheckoutInterface $prepare): Response
    {
        $data = $prepare->handler();

        if (isset($data['redirect']) && $data['redirect'] === 'cart') {
            $this->addFlash('warning', 'Tu carrito está vacío.');
            return $this->redirectToRoute('store_cart', ['_locale' => $this->getParameter('locale')]);
        }

        return $this->render('e_commerce/theme_1/checkout/checkout.html.twig', $data);
    }

    #[Route('/confirm', name: '_confirm', methods: ['POST'])]
    public function confirm(Request $request, ConfirmOrderInterface $confirm): Response
    {
        $data   = json_decode($request->getContent(), true) ?? $request->request->all();
        $result = $confirm->handler($data);

        if ($request->getContentTypeFormat() === 'json' || $request->headers->get('Accept') === 'application/json') {
            return $this->json($result);
        }

        if ($result['success'] && !empty($result['requiresPayment'])) {
            return $this->redirect($result['redirectUrl']);
        }

        if ($result['success']) {
            return $this->redirectToRoute('store_checkout_success', [
                '_locale' => $this->getParameter('locale'),
                'id'      => $result['orderId'],
            ]);
        }

        $this->addFlash('error', $result['message'] ?? 'Error al confirmar el pedido.');
        return $this->redirectToRoute('store_checkout', ['_locale' => $this->getParameter('locale')]);
    }

    #[Route('/success/{id}', name: '_success', methods: ['GET'])]
    public function success(Orders $orders): Response
    {
        if ($orders->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        return $this->render('e_commerce/theme_1/checkout/success.html.twig', ['order' => $orders]);
    }

    #[Route('/return/{id}', name: '_return', methods: ['GET'])]
    public function return(Orders $orders): Response
    {
        if ($orders->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($orders->getStatus()->getName() === 'Procesando') {
            return $this->redirectToRoute('store_checkout_success', [
                '_locale' => $this->getParameter('locale'),
                'id'      => (string) $orders->getId(),
            ]);
        }

        if ($orders->getStatus()->getName() === 'Cancelado') {
            $this->addFlash('error', 'El pago no pudo completarse.');
            return $this->redirectToRoute('store_checkout', ['_locale' => $this->getParameter('locale')]);
        }

        return $this->render('e_commerce/theme_1/checkout/pending.html.twig', ['order' => $orders]);
    }

    #[Route('/status/{id}', name: '_status', methods: ['GET'])]
    public function status(Orders $orders): JsonResponse
    {
        if ($orders->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->json(['statusName' => $orders->getStatus()->getName()]);
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

    #[Route('/api/payment-methods', name: '_api_payment_methods', methods: ['GET'])]
    public function apiPaymentMethods(
        Request $request,
        GetDomainDataInterface $getDomainData,
        PaymentMethodAvailabilityResolverInterface $paymentMethodAvailability,
        CountriesRepository $countriesRepository,
        RegionsRepository $regionsRepository,
        CitiesRepository $citiesRepository,
    ): JsonResponse {
        $countryId = $request->query->get('countryId', '');
        $regionId  = $request->query->get('regionId', '');
        $cityId    = $request->query->get('cityId', '');

        $country = $countryId ? $countriesRepository->find(StringUtil::convertToUuid($countryId)) : null;
        $region  = $regionId ? $regionsRepository->find(StringUtil::convertToUuid($regionId)) : null;
        $city    = $cityId ? $citiesRepository->find(StringUtil::convertToUuid($cityId)) : null;

        $geo            = new GeoLocation($country, $region, $city);
        $domain         = $getDomainData->getDomainCache();
        $paymentMethods = $paymentMethodAvailability->resolve($domain, $geo);

        return $this->json(array_map(fn ($pm) => [
            'id'           => (string) $pm->getId(),
            'name'         => $pm->getName(),
            'instructions' => $pm->getInstructions(),
        ], $paymentMethods));
    }
}
