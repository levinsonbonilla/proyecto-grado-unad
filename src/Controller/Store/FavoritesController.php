<?php

namespace App\Controller\Store;

use App\Interface\UseCase\Store\Favorites\GetFavoritesInterface;
use App\Interface\UseCase\Store\Favorites\ToggleFavoriteInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '{_locale<%supported_locales%>}/favorites', name: 'store_favorites')]
class FavoritesController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function list(GetFavoritesInterface $getFavorites): Response
    {
        return $this->render('e_commerce/theme_1/favorites/list.html.twig', [
            'products' => $getFavorites->handler(),
        ]);
    }

    #[Route('/toggle', name: '_toggle', methods: ['POST'])]
    public function toggle(Request $request, ToggleFavoriteInterface $toggle): JsonResponse
    {
        $data   = json_decode($request->getContent(), true) ?? [];
        $result = $toggle->handler((string) ($data['productId'] ?? ''));
        return $this->json($result);
    }
}
