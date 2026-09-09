<?php

namespace App\Handler\UseCase\Store\Checkout;

use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Orders\Orders;
use App\Entity\Products\Orders\OrdersProducts;
use App\Entity\Products\Orders\PaymentTransactions;
use App\Entity\Products\Products;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Exception\GenericException;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Service\Payments\PaymentGatewayClientInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Cart\ClearCartInterface;
use App\Interface\UseCase\Store\Cart\GetCartInterface;
use App\Interface\UseCase\Store\Checkout\ConfirmOrderInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Repository\Configurations\Globals\StatusRepository;
use App\Repository\Products\Colors\ProductsColorsRepository;
use App\Repository\Products\ProductsRepository;
use App\Repository\Tenants\Domains\PaymentMethodsRepository;
use App\Interface\Service\Currency\CurrentCurrencyResolverInterface;
use App\Service\Geo\GeoLocation;
use App\Service\Payments\PaymentMethodAvailabilityResolverInterface;
use App\Service\Products\LowStockAlertInterface;
use App\Service\Products\VariantLabelFormatterInterface;
use App\Util\StringUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ConfirmOrderUseCase implements ConfirmOrderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly GetCartInterface $getCart,
        private readonly ClearCartInterface $clearCart,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly StatusRepository $statusRepository,
        private readonly CountriesRepository $countriesRepository,
        private readonly RegionsRepository $regionsRepository,
        private readonly CitiesRepository $citiesRepository,
        private readonly PaymentMethodsRepository $paymentMethodsRepository,
        private readonly ProductsRepository $productsRepository,
        private readonly ProductsColorsRepository $productsColorsRepository,
        private readonly CustomeEntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly LogInterface $log,
        private readonly PaymentGatewayClientInterface $paymentGatewayClient,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly CurrentCurrencyResolverInterface $currencyResolver,
        private readonly VariantLabelFormatterInterface $variantLabelFormatter,
        private readonly LowStockAlertInterface $lowStockAlert,
        private readonly PaymentMethodAvailabilityResolverInterface $paymentMethodAvailability,
        private readonly TranslatorInterface $translator,
    ) {}

    public function handler(array $data): array
    {
        try {

            $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'es';

            $user = $this->security->getUser();
            if ($user === null) {
                return ['success' => false, 'message' => $this->translator->trans('not_authenticated', [], 'store', $locale)];
            }

            $cart = $this->getCart->handler();
            if (empty($cart['items'])) {
                return ['success' => false, 'message' => $this->translator->trans('cart_empty', [], 'store', $locale)];
            }

            $address   = trim($data['address'] ?? '');
            $countryId = $data['countryId'] ?? null;
            $regionId  = $data['regionId']  ?? null;
            $cityId    = $data['cityId']    ?? null;

            if (empty($address) || empty($countryId) || empty($regionId) || empty($cityId)) {
                return ['success' => false, 'message' => $this->translator->trans('shipping_fields_required', [], 'store', $locale)];
            }

            $country = $this->countriesRepository->find(StringUtil::convertToUuid($countryId));
            $region  = $this->regionsRepository->find(StringUtil::convertToUuid($regionId));
            $city    = $this->citiesRepository->find(StringUtil::convertToUuid($cityId));

            if ($country === null || $region === null || $city === null) {
                return ['success' => false, 'message' => $this->translator->trans('invalid_shipping_location', [], 'store', $locale)];
            }

            $status = $this->statusRepository->findOneBy(['name' => 'Pendiente', 'active' => true]);
            if ($status === null) {
                throw new GenericException($this->translator->trans('pending_status_not_found', [], 'store', $locale), 500);
            }

            $products = [];
            $productColors = [];
            foreach ($cart['items'] as $item) {
                $product = $this->productsRepository->find(StringUtil::convertToUuid((string) $item['productId']));
                if ($product === null) {
                    continue;
                }
                $products[(string) $item['cartItemId']] = $product;

                $productColor = null;
                if (!empty($item['colorId'])) {
                    $productColor = $this->productsColorsRepository->find(StringUtil::convertToUuid((string) $item['colorId']));
                }
                $productColors[(string) $item['cartItemId']] = $productColor;

                $stock = $productColor !== null ? $productColor->getStock() : $product->getStock();
                if ($stock !== null && $stock < (int) $item['quantity']) {
                    return [
                        'success' => false,
                        'message' => sprintf(
                            'No hay suficiente stock de "%s"%s. Disponible: %d, solicitado: %d.',
                            $product->getName(),

                            ($productColor !== null && $productColor->getColor() !== null) ? ' (' . $productColor->getColor()->getName() . ')' : '',
                            $stock,
                            (int) $item['quantity']
                        ),
                    ];
                }
            }

            $paymentMethod = null;
            if (!empty($data['paymentMethodId'])) {
                $paymentMethod = $this->paymentMethodsRepository->find(StringUtil::convertToUuid($data['paymentMethodId']));
            }

            if ($paymentMethod !== null && !$this->paymentMethodAvailability->isAvailable($paymentMethod, new GeoLocation($country, $region, $city))) {
                return [
                    'success' => false,
                    'message' => $this->translator->trans('payment_method_not_available_for_location', [], 'store', $locale),
                ];
            }

            $totalAmount = (string) $cart['subtotal'];

            $order = (new Orders())->add(
                $user,
                $totalAmount,
                $address,
                $country,
                $region,
                $city,
                $status,
            );
            $this->em->add($order, false);

            $lowStockProducts = [];
            $lowStockBlocks = [];

            foreach ($cart['items'] as $item) {
                $product = $products[(string) $item['cartItemId']] ?? null;
                if ($product === null) {
                    continue;
                }
                $productColor = $productColors[(string) $item['cartItemId']] ?? null;

                $orderProduct = (new OrdersProducts())->add(
                    $order,
                    $product,
                    (string) $item['quantity'],
                    (string) $item['publicPrice'],
                    (string) $item['itemTotal'],
                    $productColor,
                );
                $this->em->add($orderProduct, false);

                if ($productColor !== null) {
                    $productColor->decreaseStock((int) $item['quantity']);
                    $this->em->add($productColor, false);
                    if ($this->lowStockAlert->markBlockIfLow($productColor)) {
                        $lowStockBlocks[] = $productColor;
                    }
                }
                $product->decreaseStock((int) $item['quantity']);
                $this->em->add($product, false);
                if ($this->lowStockAlert->markProductIfLow($product)) {
                    $lowStockProducts[] = $product;
                }
            }

            $tx = null;
            if ($paymentMethod !== null || $cart['subtotal'] > 0) {
                $txStatus = $this->statusRepository->findOneBy(['name' => 'Pendiente', 'active' => true]);
                $tx = (new PaymentTransactions())->add($order, $txStatus, $totalAmount, $paymentMethod);
                $this->em->add($tx, false);
            }

            if ($paymentMethod !== null && $paymentMethod->getProvider() === PaymentMethods::PROVIDER_MOCK_GATEWAY && $tx !== null) {
                $this->em->flush();

                $returnUrl = $this->urlGenerator->generate(
                    'store_checkout_return',
                    ['id' => (string) $order->getId(), '_locale' => $locale],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
                $webhookUrl = $this->urlGenerator->generate(
                    'store_checkout_webhook_payment',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );

                $intent = $this->paymentGatewayClient->createIntent(
                    (string) $order->getId(),
                    (int) $totalAmount,
                    $this->currencyResolver->resolve($this->getDomainData->getDomainCache())->code,
                    $returnUrl,
                    $webhookUrl,
                );

                $tx->setGatewayData($intent->id, null);
                $this->em->add($tx, true);

                $this->notifyLowStock($lowStockProducts, $lowStockBlocks);

                $this->clearCart->handler();

                return [
                    'success'         => true,
                    'orderId'         => (string) $order->getId(),
                    'requiresPayment' => true,
                    'redirectUrl'     => $intent->redirectUrl,
                ];
            }

            $this->em->flush();

            $this->notifyLowStock($lowStockProducts, $lowStockBlocks);

            $this->clearCart->handler();

            $this->sendConfirmationEmail($user, $order, $cart);

            return ['success' => true, 'orderId' => (string) $order->getId()];
        } catch (GenericException $e) {
            $this->log->handler($e);
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $th) {
            $log = $this->log->handler($th);
            return ['success' => false, 'message' => $this->translator->trans('order_processing_error_ref', ['%ref%' => (string) $log?->getShortReference()], 'store', $locale ?? 'es')];
        }
    }

    private function notifyLowStock(array $lowStockProducts, array $lowStockBlocks): void
    {
        foreach ($lowStockProducts as $product) {
            $this->lowStockAlert->notifyProduct($product);
        }
        foreach ($lowStockBlocks as $block) {
            $this->lowStockAlert->notifyBlock($block);
        }
    }

    private function sendConfirmationEmail(object $user, Orders $order, array $cart): void
    {
        try {

            $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'es';
            $domain = $this->getDomainData->getDomainCache();
            $email  = (new Email())
                ->from('noreply@' . $domain->getDomain())
                ->to($user->getEmail())
                ->subject($this->translator->trans('order_email_subject', ['%orderId%' => substr((string) $order->getId(), 0, 8)], 'store', $locale))
                ->html($this->buildEmailHtml($user, $order, $cart, $locale));
            $this->mailer->send($email);
        } catch (\Throwable) {

        }
    }

    private function buildEmailHtml(object $user, Orders $order, array $cart, string $locale): string
    {
        $currency = $this->currencyResolver->resolve($this->getDomainData->getDomainCache());
        $divisor  = 10 ** $currency->decimalPlaces;

        $orderId  = substr((string) $order->getId(), 0, 8);
        $total    = number_format($cart['subtotal'] / $divisor, $currency->decimalPlaces, $currency->decimalSeparator, $currency->thousandsSeparator);
        $itemRows = '';
        foreach ($cart['items'] as $item) {
            $price = number_format($item['publicPrice'] / $divisor, $currency->decimalPlaces, $currency->decimalSeparator, $currency->thousandsSeparator);
            $line  = number_format($item['itemTotal'] / $divisor, $currency->decimalPlaces, $currency->decimalSeparator, $currency->thousandsSeparator);
            $name  = $item['name'] . $this->variantLabelFormatter->format($item['colorName'] ?? null, $item['medidaName'] ?? null);
            $itemRows .= "<tr><td>{$name}</td><td>{$item['quantity']}</td><td>{$currency->symbol}{$price}</td><td>{$currency->symbol}{$line}</td></tr>";
        }

        $t = fn (string $key, array $params = []) => $this->translator->trans($key, $params, 'store', $locale);

        return "
<html><body style='font-family:sans-serif;color:#333'>
<h2>{$t('order_email_thanks_heading')}</h2>
<p>{$t('order_email_greeting', ['%name%' => $user->getName(), '%orderId%' => $orderId])}</p>
<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse;width:100%'>
<tr style='background:#f5f5f5'><th>{$t('order_email_col_product')}</th><th>{$t('order_email_col_quantity')}</th><th>{$t('order_email_col_price')}</th><th>{$t('total')}</th></tr>
{$itemRows}
<tr><td colspan='3'><strong>{$t('total')}</strong></td><td><strong>{$currency->symbol}{$total}</strong></td></tr>
</table>
<p>{$t('order_email_shipping_address')} {$order->getShippingAddress()}</p>
<p>{$t('order_email_status')} <strong>{$order->getStatus()->getName()}</strong></p>
<p>{$t('order_email_footer')}</p>
</body></html>";
    }
}
