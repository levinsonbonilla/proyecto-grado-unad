<?php

namespace App\Controller\Store;

use App\Interface\UseCase\Store\Cart\AddToCartInterface;
use App\Interface\UseCase\Store\Cart\GetCartInterface;
use App\Interface\UseCase\Store\Cart\RemoveFromCartInterface;
use App\Interface\UseCase\Store\Cart\UpdateCartQuantityInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '{_locale<%supported_locales%>}/cart', name: 'store_cart')]
class CartController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function cart(GetCartInterface $getCart): Response
    {
        return $this->render('e_commerce/theme_1/cart/cart.html.twig', $getCart->handler());
    }

    #[Route('/add', name: '_add', methods: ['POST'])]
    public function add(Request $request, AddToCartInterface $addToCart): JsonResponse
    {
        $data     = json_decode($request->getContent(), true) ?? [];
        $colorId  = trim((string) ($data['colorId'] ?? ''));
        $result   = $addToCart->handler(
            productId: (string) ($data['productId'] ?? ''),
            quantity:  max(1, (int) ($data['quantity'] ?? 1)),
            colorId:   $colorId !== '' ? $colorId : null,
        );
        return $this->json($result);
    }

    #[Route('/update', name: '_update', methods: ['POST'])]
    public function update(Request $request, UpdateCartQuantityInterface $updateCart): JsonResponse
    {
        $data   = json_decode($request->getContent(), true) ?? [];
        $result = $updateCart->handler(
            cartItemId: (string) ($data['cartItemId'] ?? ''),
            quantity:   max(1, (int) ($data['quantity'] ?? 1)),
        );
        return $this->json($result);
    }

    #[Route('/remove', name: '_remove', methods: ['POST'])]
    public function remove(Request $request, RemoveFromCartInterface $removeFromCart): JsonResponse
    {
        $data   = json_decode($request->getContent(), true) ?? [];
        $result = $removeFromCart->handler((string) ($data['cartItemId'] ?? ''));
        return $this->json($result);
    }
}
