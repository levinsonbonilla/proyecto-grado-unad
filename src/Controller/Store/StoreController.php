<?php

namespace App\Controller\Store;

use App\Entity\Products\Categories\Categories;
use App\Entity\Products\Products;
use App\Interface\UseCase\Store\Cart\GetCartInterface;
use App\Interface\UseCase\Store\Products\GetPublicCategoriesInterface;
use App\Interface\UseCase\Store\Products\GetPublicHomeInterface;
use App\Interface\UseCase\Store\Products\GetPublicProductDetailInterface;
use App\Interface\UseCase\Store\Products\GetPublicProductsInterface;
use App\Interface\UseCase\Store\Products\GetPublicSlidesInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '{_locale<%supported_locales%>}', name: 'store')]
class StoreController extends AbstractController
{
    #[Route('', name: '')]
    public function home(GetPublicHomeInterface $home): Response
    {
        $data = $home->handler();
        return $this->render('e_commerce/theme_1/home.html.twig', $data);
    }

    #[Route('/shop', name: '_shop')]
    public function shop(
        Request $request,
        GetPublicProductsInterface $products,
        GetPublicCategoriesInterface $categories,
    ): Response {
        $data = $products->handler(
            categoryId: $request->query->get('category'),
            search:     $request->query->get('q'),
            minPrice:   $request->query->getInt('min') ?: null,
            maxPrice:   $request->query->getInt('max') ?: null,
            page:       max(1, $request->query->getInt('page', 1)),
        );
        return $this->render('e_commerce/theme_1/shop.html.twig', [
            ...$data,
            'categories' => $categories->handler(),
        ]);
    }

    #[Route('/category/{id}', name: '_category')]
    public function category(
        Categories $category,
        Request $request,
        GetPublicProductsInterface $products,
        GetPublicCategoriesInterface $categories,
    ): Response {
        $data = $products->handler(
            categoryId: (string) $category->getId(),
            search:     $request->query->get('q'),
            page:       max(1, $request->query->getInt('page', 1)),
        );
        return $this->render('e_commerce/theme_1/shop.html.twig', [
            ...$data,
            'categories'       => $categories->handler(),
            'activeCategory'   => $category,
        ]);
    }

    #[Route('/product/{id}', name: '_product')]
    public function product(Products $product, GetPublicProductDetailInterface $detail): Response
    {

        if ($product->isOutOfStock()) {
            throw $this->createNotFoundException();
        }

        return $this->render('e_commerce/theme_1/product_detail.html.twig', $detail->handler($product));
    }

    #[Route('/search', name: '_search')]
    public function search(
        Request $request,
        GetPublicProductsInterface $products,
        GetPublicCategoriesInterface $categories,
    ): Response {
        $data = $products->handler(
            search: $request->query->get('q'),
            page:   max(1, $request->query->getInt('page', 1)),
        );
        return $this->render('e_commerce/theme_1/shop.html.twig', [
            ...$data,
            'categories'   => $categories->handler(),
            'searchQuery'  => $request->query->get('q', ''),
        ]);
    }

    #[Route('/api/products', name: '_api_products', methods: ['GET', 'POST'])]
    public function apiProducts(Request $request, GetPublicProductsInterface $products): JsonResponse
    {
        $data = $products->handler(
            categoryId: $request->get('category'),
            search:     $request->get('q'),
            minPrice:   $request->get('min') ? (int) $request->get('min') : null,
            maxPrice:   $request->get('max') ? (int) $request->get('max') : null,
            page:       max(1, (int) $request->get('page', 1)),
            limit:      min(50, max(1, (int) $request->get('limit', 12))),
        );
        return $this->json($data);
    }

    #[Route('/api/cart/count', name: '_api_cart_count', methods: ['GET'])]
    public function apiCartCount(GetCartInterface $getCart): JsonResponse
    {
        $cart = $getCart->handler();
        return $this->json(['count' => $cart['count']]);
    }

    #[Route('/about', name: '_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('e_commerce/theme_1/about.html.twig');
    }

    #[Route('/contact', name: '_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request): Response
    {
        return $this->render('e_commerce/theme_1/contact.html.twig');
    }

    #[Route('/api/autocomplete', name: '_api_autocomplete', methods: ['GET'])]
    public function apiAutocomplete(Request $request, GetPublicProductsInterface $products): JsonResponse
    {
        $q = trim($request->query->get('q', ''));
        if (strlen($q) < 2) {
            return $this->json([]);
        }
        $data = $products->handler(search: $q, page: 1, limit: 8);
        $suggestions = array_map(fn($p) => [
            'id'    => $p['id'] ?? null,
            'name'  => $p['name'] ?? '',
            'price' => $p['publicPrice'] ?? null,
        ], $data['items'] ?? []);
        return $this->json($suggestions);
    }
}
