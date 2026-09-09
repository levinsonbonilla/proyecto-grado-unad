<?php

namespace App\Handler\UseCase\Store\Checkout;

use App\Entity\Products\Orders\Orders;
use App\Exception\GenericException;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Checkout\HandlePaymentWebhookInterface;
use App\Repository\Configurations\Globals\StatusRepository;
use App\Repository\Products\Orders\OrdersProductsRepository;
use App\Repository\Products\Orders\PaymentTransactionsRepository;
use App\Interface\Service\Currency\CurrentCurrencyResolverInterface;
use App\Service\Products\VariantLabelFormatterInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class HandlePaymentWebhookUseCase implements HandlePaymentWebhookInterface
{
    private const TERMINAL_STATUSES = ['Procesando', 'Cancelado'];

    public function __construct(
        private readonly PaymentTransactionsRepository $paymentTransactionsRepository,
        private readonly OrdersProductsRepository $ordersProductsRepository,
        private readonly StatusRepository $statusRepository,
        private readonly CustomeEntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly LogInterface $log,
        private readonly string $gatewaySecret,
        private readonly CurrentCurrencyResolverInterface $currencyResolver,
        private readonly VariantLabelFormatterInterface $variantLabelFormatter,
    ) {
    }

    public function handler(string $rawBody, string $signature): array
    {
        try {
            if (empty($signature) || !hash_equals(hash_hmac('sha256', $rawBody, $this->gatewaySecret), $signature)) {
                throw new GenericException('Firma inválida.', 400);
            }

            $payload = json_decode($rawBody, true);
            if (!is_array($payload)) {
                throw new GenericException('Payload inválido.', 400);
            }

            $intentId = $payload['intentId'] ?? null;
            $outcome  = $payload['status'] ?? null;

            if (empty($intentId) || !in_array($outcome, ['succeeded', 'failed'], true)) {
                throw new GenericException('Payload incompleto.', 400);
            }

            $transaction = $this->paymentTransactionsRepository->findByGatewayReference($intentId);
            if ($transaction === null) {

                return ['success' => true];
            }

            if (in_array($transaction->getStatus()->getName(), self::TERMINAL_STATUSES, true)) {
                return ['success' => true];
            }

            $newStatusName = $outcome === 'succeeded' ? 'Procesando' : 'Cancelado';
            $newStatus = $this->statusRepository->findOneBy(['name' => $newStatusName, 'active' => true]);
            if ($newStatus === null) {
                throw new GenericException('Estado no disponible.', 500);
            }

            $transaction->edit($newStatus);
            $transaction->setGatewayData($transaction->getGatewayReference(), $rawBody);
            $this->em->add($transaction, false);

            $order = $transaction->getOrders();
            $order->edit($newStatus);
            $this->em->add($order, true);

            if ($outcome === 'succeeded') {
                $this->sendConfirmationEmail($order);
            }

            return ['success' => true];
        } catch (GenericException $e) {
            $this->log->handler($e);
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false];
        }
    }

    private function sendConfirmationEmail(Orders $order): void
    {
        try {
            $domain = $this->getDomainData->getDomainCache();
            $items  = $this->ordersProductsRepository->findByOrder($order);

            $email = (new Email())
                ->from('noreply@' . $domain->getDomain())
                ->to($order->getUser()->getEmail())
                ->subject('Confirmación de tu pedido #' . substr((string) $order->getId(), 0, 8))
                ->html($this->buildEmailHtml($order, $items));

            $this->mailer->send($email);
        } catch (\Throwable) {

        }
    }

    private function buildEmailHtml(Orders $order, array $items): string
    {
        $currency = $this->currencyResolver->resolve($this->getDomainData->getDomainCache());
        $divisor  = 10 ** $currency->decimalPlaces;

        $orderId  = substr((string) $order->getId(), 0, 8);
        $total    = number_format(((int) $order->getTotalAmount()) / $divisor, $currency->decimalPlaces, $currency->decimalSeparator, $currency->thousandsSeparator);
        $itemRows = '';
        foreach ($items as $item) {
            $unit  = number_format(((int) $item['unitPrice']) / $divisor, $currency->decimalPlaces, $currency->decimalSeparator, $currency->thousandsSeparator);
            $line  = number_format(((int) $item['totalPrice']) / $divisor, $currency->decimalPlaces, $currency->decimalSeparator, $currency->thousandsSeparator);
            $name  = $item['productName'] . $this->variantLabelFormatter->format($item['colorName'] ?? null, $item['medidaName'] ?? null);
            $itemRows .= "<tr><td>{$name}</td><td>{$item['quantity']}</td><td>{$currency->symbol}{$unit}</td><td>{$currency->symbol}{$line}</td></tr>";
        }

        return "
<html><body style='font-family:sans-serif;color:#333'>
<h2>¡Gracias por tu pago!</h2>
<p>Confirmamos el pago de tu pedido <strong>#{$orderId}</strong>.</p>
<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse;width:100%'>
<tr style='background:#f5f5f5'><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Total</th></tr>
{$itemRows}
<tr><td colspan='3'><strong>Total</strong></td><td><strong>{$currency->symbol}{$total}</strong></td></tr>
</table>
<p>Pronto recibirás una actualización del estado de tu pedido.</p>
</body></html>";
    }
}
