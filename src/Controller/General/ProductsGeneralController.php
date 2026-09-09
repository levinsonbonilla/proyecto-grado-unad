<?php

namespace App\Controller\General;

use App\Entity\Products\Products;
use App\Interface\UseCase\General\GetProductInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '{_locale<%supported_locales%>}/general', name: 'general')]
class ProductsGeneralController extends AbstractController
{
    public function __construct() {
    }

    #[Route(path: '/product/{product}', name: '_product', methods: ['GET'])]
    public function product(Products $product, GetProductInterface $getProduct): Response     {

        if ($product->isOutOfStock()) {
            throw $this->createNotFoundException();
        }

        return $this->json($getProduct->handler($product));
    }
}