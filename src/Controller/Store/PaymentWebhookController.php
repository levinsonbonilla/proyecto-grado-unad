<?php

namespace App\Controller\Store;

use App\Interface\UseCase\Store\Checkout\HandlePaymentWebhookInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentWebhookController extends AbstractController
{
    #[Route('/checkout/webhook/payment', name: 'store_checkout_webhook_payment', methods: ['POST'], priority: 1)]
    public function __invoke(Request $request, HandlePaymentWebhookInterface $handler): Response
    {
        $signature = $request->headers->get('X-Gateway-Signature', '');
        $result    = $handler->handler($request->getContent(), $signature);

        return new Response('', $result['success'] ? 200 : 400);
    }
}
