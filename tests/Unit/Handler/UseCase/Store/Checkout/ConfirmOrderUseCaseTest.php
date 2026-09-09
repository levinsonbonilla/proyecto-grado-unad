<?php

namespace App\Tests\Unit\Handler\UseCase\Store\Checkout;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Entity\Configurations\Globals\Status;
use App\Entity\Products\Products;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Entity\Users\Users;
use App\Handler\UseCase\Store\Checkout\ConfirmOrderUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Service\Payments\PaymentGatewayClientInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Cart\ClearCartInterface;
use App\Interface\UseCase\Store\Cart\GetCartInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Repository\Configurations\Globals\StatusRepository;
use App\Repository\Products\Colors\ProductsColorsRepository;
use App\Repository\Products\ProductsRepository;
use App\Entity\Products\Colors\ProductsColors;
use App\Repository\Tenants\Domains\PaymentMethodsRepository;
use App\Service\Currency\CurrencyFormat;
use App\Interface\Service\Currency\CurrentCurrencyResolverInterface;
use App\Service\Payments\PaymentIntentResult;
use App\Service\Payments\PaymentMethodAvailabilityResolverInterface;
use App\Service\Products\LowStockAlertInterface;
use App\Service\Products\VariantLabelFormatterInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class ConfirmOrderUseCaseTest extends TestCase
{
    private Security&MockObject $security;
    private GetCartInterface&MockObject $getCart;
    private ClearCartInterface&MockObject $clearCart;
    private GetDomainDataInterface&MockObject $getDomainData;
    private StatusRepository&MockObject $statusRepository;
    private CountriesRepository&MockObject $countriesRepository;
    private RegionsRepository&MockObject $regionsRepository;
    private CitiesRepository&MockObject $citiesRepository;
    private PaymentMethodsRepository&MockObject $paymentMethodsRepository;
    private ProductsRepository&MockObject $productsRepository;
    private ProductsColorsRepository&MockObject $productsColorsRepository;
    private CustomeEntityManagerInterface&MockObject $em;
    private MailerInterface&MockObject $mailer;
    private LogInterface&MockObject $log;
    private PaymentGatewayClientInterface&MockObject $paymentGatewayClient;
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private RequestStack&MockObject $requestStack;
    private CurrentCurrencyResolverInterface&MockObject $currencyResolver;
    private VariantLabelFormatterInterface&MockObject $variantLabelFormatter;
    private LowStockAlertInterface&MockObject $lowStockAlert;
    private PaymentMethodAvailabilityResolverInterface&MockObject $paymentMethodAvailability;
    private TranslatorInterface&MockObject $translator;
    private ConfirmOrderUseCase $useCase;

    protected function setUp(): void
    {
        $this->security             = $this->createMock(Security::class);
        $this->getCart              = $this->createMock(GetCartInterface::class);
        $this->clearCart            = $this->createMock(ClearCartInterface::class);
        $this->getDomainData        = $this->createMock(GetDomainDataInterface::class);
        $this->statusRepository     = $this->createMock(StatusRepository::class);
        $this->countriesRepository  = $this->createMock(CountriesRepository::class);
        $this->regionsRepository    = $this->createMock(RegionsRepository::class);
        $this->citiesRepository     = $this->createMock(CitiesRepository::class);
        $this->paymentMethodsRepository = $this->createMock(PaymentMethodsRepository::class);
        $this->productsRepository   = $this->createMock(ProductsRepository::class);
        $this->productsColorsRepository = $this->createMock(ProductsColorsRepository::class);
        $this->em                   = $this->createMock(CustomeEntityManagerInterface::class);
        $this->mailer               = $this->createMock(MailerInterface::class);
        $this->log                  = $this->createMock(LogInterface::class);
        $this->paymentGatewayClient = $this->createMock(PaymentGatewayClientInterface::class);
        $this->urlGenerator         = $this->createMock(UrlGeneratorInterface::class);
        $this->requestStack         = $this->createMock(RequestStack::class);
        $this->currencyResolver     = $this->createMock(CurrentCurrencyResolverInterface::class);
        $this->currencyResolver->method('resolve')->willReturn(CurrencyFormat::legacyDefault());
        $this->variantLabelFormatter = $this->createMock(VariantLabelFormatterInterface::class);
        $this->variantLabelFormatter->method('format')->willReturn('');

        $this->lowStockAlert = $this->createMock(LowStockAlertInterface::class);
        $this->paymentMethodAvailability = $this->createMock(PaymentMethodAvailabilityResolverInterface::class);

        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnCallback(
            function (string $id, array $parameters = []): string {
                return match ($id) {
                    'not_authenticated' => 'No autenticado.',
                    'cart_empty' => 'El carrito está vacío.',
                    'shipping_fields_required' => 'Completa todos los campos de envío.',
                    'invalid_shipping_location' => 'Ubicación de envío inválida.',
                    'pending_status_not_found' => 'Estado "Pendiente" no encontrado.',
                    'payment_method_not_available_for_location' => 'El método de pago seleccionado no está disponible para tu ubicación de envío.',
                    'order_processing_error_ref' => 'Error al procesar el pedido. Ref: ' . ($parameters['%ref%'] ?? ''),
                    default => $id,
                };
            }
        );

        $this->useCase = new ConfirmOrderUseCase(
            $this->security,
            $this->getCart,
            $this->clearCart,
            $this->getDomainData,
            $this->statusRepository,
            $this->countriesRepository,
            $this->regionsRepository,
            $this->citiesRepository,
            $this->paymentMethodsRepository,
            $this->productsRepository,
            $this->productsColorsRepository,
            $this->em,
            $this->mailer,
            $this->log,
            $this->paymentGatewayClient,
            $this->urlGenerator,
            $this->requestStack,
            $this->currencyResolver,
            $this->variantLabelFormatter,
            $this->lowStockAlert,
            $this->paymentMethodAvailability,
            $this->translator,
        );
    }

    private function makeValidData(): array
    {
        return [
            'address'   => 'Calle 123',
            'countryId' => (string) Uuid::v4(),
            'regionId'  => (string) Uuid::v4(),
            'cityId'    => (string) Uuid::v4(),
        ];
    }

    private function makeCartWithItems(): array
    {
        $productId = (string) Uuid::v4();
        return [
            'items'    => [
                ['cartItemId' => $productId, 'productId' => $productId, 'name' => 'Prod A', 'quantity' => 1, 'publicPrice' => 5000, 'itemTotal' => 5000],
            ],
            'subtotal' => 5000,
        ];
    }

    private function makeGeo(): array
    {
        $country = $this->createMock(Countries::class);
        $region  = $this->createMock(Regions::class);
        $city    = $this->createMock(Cities::class);
        return [$country, $region, $city];
    }

    public function testHandlerReturnsFalseWhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertFalse($result['success']);
    }

    public function testHandlerReturnsFalseWhenCartIsEmpty(): void
    {
        $user = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($user);
        $this->getCart->method('handler')->willReturn(['items' => [], 'subtotal' => 0]);

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('vacío', $result['message']);
    }

    public function testHandlerReturnsFalseWhenAddressIsIncomplete(): void
    {
        $user = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($user);
        $this->getCart->method('handler')->willReturn($this->makeCartWithItems());

        $result = $this->useCase->handler(['address' => '', 'countryId' => '', 'regionId' => '', 'cityId' => '']);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('envío', $result['message']);
    }

    public function testHandlerReturnsFalseWhenLocationNotFound(): void
    {
        $user = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($user);
        $this->getCart->method('handler')->willReturn($this->makeCartWithItems());
        $this->countriesRepository->method('find')->willReturn(null);

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Ubicación', $result['message']);
    }

    public function testHandlerReturnsFalseWhenStatusNotFound(): void
    {
        $user = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($user);
        $this->getCart->method('handler')->willReturn($this->makeCartWithItems());

        [$country, $region, $city] = $this->makeGeo();
        $this->countriesRepository->method('find')->willReturn($country);
        $this->regionsRepository->method('find')->willReturn($region);
        $this->citiesRepository->method('find')->willReturn($city);
        $this->statusRepository->method('findOneBy')->willReturn(null);
        $this->log->method('handler')->willReturn(null);

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertFalse($result['success']);
    }

    public function testHandlerCreatesOrderSuccessfully(): void
    {
        $user = $this->createMock(Users::class);
        $user->method('getName')->willReturn('Test');
        $user->method('getEmail')->willReturn('test@test.com');
        $this->security->method('getUser')->willReturn($user);
        $this->getCart->method('handler')->willReturn($this->makeCartWithItems());

        [$country, $region, $city] = $this->makeGeo();
        $country->method('getName')->willReturn('Colombia');
        $this->countriesRepository->method('find')->willReturn($country);
        $this->regionsRepository->method('find')->willReturn($region);
        $this->citiesRepository->method('find')->willReturn($city);

        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn('Pendiente');
        $this->statusRepository->method('findOneBy')->willReturn($status);

        $this->em->expects($this->atLeastOnce())->method('add');
        $this->em->expects($this->once())->method('flush');
        $this->clearCart->expects($this->once())->method('handler');

        $domain = $this->createMock(\App\Entity\Tenants\Domains\Domains::class);
        $domain->method('getDomain')->willReturn('localhost');
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('orderId', $result);
    }

    public function testHandlerRedirectsToGatewayWhenPaymentMethodIsMockGateway(): void
    {
        $user = $this->createMock(Users::class);
        $user->method('getEmail')->willReturn('test@test.com');
        $this->security->method('getUser')->willReturn($user);
        $this->getCart->method('handler')->willReturn($this->makeCartWithItems());

        [$country, $region, $city] = $this->makeGeo();
        $this->countriesRepository->method('find')->willReturn($country);
        $this->regionsRepository->method('find')->willReturn($region);
        $this->citiesRepository->method('find')->willReturn($city);

        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn('Pendiente');
        $this->statusRepository->method('findOneBy')->willReturn($status);

        $paymentMethod = $this->createMock(PaymentMethods::class);
        $paymentMethod->method('getProvider')->willReturn(PaymentMethods::PROVIDER_MOCK_GATEWAY);
        $this->paymentMethodsRepository->method('find')->willReturn($paymentMethod);
        $this->paymentMethodAvailability->method('isAvailable')->willReturn(true);

        $request = $this->createMock(\Symfony\Component\HttpFoundation\Request::class);
        $request->method('getLocale')->willReturn('es');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator->method('generate')->willReturn('https://proyecto-grado-unad.test/es/checkout/return/x');

        $this->paymentGatewayClient->expects($this->once())
            ->method('createIntent')
            ->willReturn(new PaymentIntentResult('pi_123', 'https://gateway.test/pay/pi_123', 'requires_action'));

        $this->mailer->expects($this->never())->method('send');
        $this->clearCart->expects($this->once())->method('handler');

        $data = $this->makeValidData();
        $data['paymentMethodId'] = (string) Uuid::v4();

        $result = $this->useCase->handler($data);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['requiresPayment']);
        $this->assertSame('https://gateway.test/pay/pi_123', $result['redirectUrl']);
    }

    public function testHandlerRejectsWhenStockInsufficientForTrackedProduct(): void
    {
        $user = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($user);

        $productId = (string) Uuid::v4();
        $this->getCart->method('handler')->willReturn([
            'items' => [
                ['cartItemId' => $productId, 'productId' => $productId, 'name' => 'Prod A', 'quantity' => 5, 'publicPrice' => 5000, 'itemTotal' => 25000],
            ],
            'subtotal' => 25000,
        ]);

        [$country, $region, $city] = $this->makeGeo();
        $this->countriesRepository->method('find')->willReturn($country);
        $this->regionsRepository->method('find')->willReturn($region);
        $this->citiesRepository->method('find')->willReturn($city);

        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn('Pendiente');
        $this->statusRepository->method('findOneBy')->willReturn($status);

        $product = $this->createMock(Products::class);
        $product->method('getName')->willReturn('Prod A');
        $product->method('getStock')->willReturn(2);
        $product->expects($this->never())->method('decreaseStock');
        $this->productsRepository->method('find')->willReturn($product);

        $this->em->expects($this->never())->method('flush');
        $this->clearCart->expects($this->never())->method('handler');

        $result = $this->useCase->handler($this->makeValidData());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('stock', $result['message']);
        $this->assertStringContainsString('Prod A', $result['message']);
    }

    public function testHandlerCreatesOrderWhenProductStockIsNull(): void
    {
        $user = $this->createMock(Users::class);
        $user->method('getName')->willReturn('Test');
        $user->method('getEmail')->willReturn('test@test.com');
        $this->security->method('getUser')->willReturn($user);
        $this->getCart->method('handler')->willReturn($this->makeCartWithItems());

        [$country, $region, $city] = $this->makeGeo();
        $country->method('getName')->willReturn('Colombia');
        $this->countriesRepository->method('find')->willReturn($country);
        $this->regionsRepository->method('find')->willReturn($region);
        $this->citiesRepository->method('find')->willReturn($city);

        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn('Pendiente');
        $this->statusRepository->method('findOneBy')->willReturn($status);

        $product = $this->createMock(Products::class);
        $product->method('getStock')->willReturn(null);
        $product->expects($this->once())->method('decreaseStock');

        $this->productsRepository->method('find')->willReturn($product);

        $domain = $this->createMock(\App\Entity\Tenants\Domains\Domains::class);
        $domain->method('getDomain')->willReturn('localhost');
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertTrue($result['success']);
    }

    public function testHandlerDecreasesStockOnSuccessfulConfirmation(): void
    {
        $user = $this->createMock(Users::class);
        $user->method('getName')->willReturn('Test');
        $user->method('getEmail')->willReturn('test@test.com');
        $this->security->method('getUser')->willReturn($user);

        $productId = (string) Uuid::v4();
        $this->getCart->method('handler')->willReturn([
            'items' => [
                ['cartItemId' => $productId, 'productId' => $productId, 'name' => 'Prod A', 'quantity' => 2, 'publicPrice' => 5000, 'itemTotal' => 10000],
            ],
            'subtotal' => 10000,
        ]);

        [$country, $region, $city] = $this->makeGeo();
        $country->method('getName')->willReturn('Colombia');
        $this->countriesRepository->method('find')->willReturn($country);
        $this->regionsRepository->method('find')->willReturn($region);
        $this->citiesRepository->method('find')->willReturn($city);

        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn('Pendiente');
        $this->statusRepository->method('findOneBy')->willReturn($status);

        $product = $this->createMock(Products::class);
        $product->method('getStock')->willReturn(5);
        $product->expects($this->once())->method('decreaseStock')->with(2);
        $this->productsRepository->method('find')->willReturn($product);

        $domain = $this->createMock(\App\Entity\Tenants\Domains\Domains::class);
        $domain->method('getDomain')->willReturn('localhost');
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertTrue($result['success']);
    }

    public function testHandlerDecreasesGeneralStockEvenWithColorSelected(): void
    {
        $user = $this->createMock(Users::class);
        $user->method('getName')->willReturn('Test');
        $user->method('getEmail')->willReturn('test@test.com');
        $this->security->method('getUser')->willReturn($user);

        $productId = (string) Uuid::v4();
        $colorId   = (string) Uuid::v4();
        $this->getCart->method('handler')->willReturn([
            'items' => [
                ['cartItemId' => $productId, 'productId' => $productId, 'colorId' => $colorId, 'name' => 'Prod A', 'quantity' => 2, 'publicPrice' => 5000, 'itemTotal' => 10000],
            ],
            'subtotal' => 10000,
        ]);

        [$country, $region, $city] = $this->makeGeo();
        $country->method('getName')->willReturn('Colombia');
        $this->countriesRepository->method('find')->willReturn($country);
        $this->regionsRepository->method('find')->willReturn($region);
        $this->citiesRepository->method('find')->willReturn($city);

        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn('Pendiente');
        $this->statusRepository->method('findOneBy')->willReturn($status);

        $product = $this->createMock(Products::class);
        $product->method('getStock')->willReturn(20);
        $product->expects($this->once())->method('decreaseStock')->with(2);
        $this->productsRepository->method('find')->willReturn($product);

        $productColor = $this->createMock(ProductsColors::class);
        $productColor->method('getStock')->willReturn(10);
        $productColor->expects($this->once())->method('decreaseStock')->with(2);
        $this->productsColorsRepository->method('find')->willReturn($productColor);

        $domain = $this->createMock(\App\Entity\Tenants\Domains\Domains::class);
        $domain->method('getDomain')->willReturn('localhost');
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertTrue($result['success']);
    }

    public function testHandlerNotifiesLowStockForProductAfterFlush(): void
    {
        $user = $this->createMock(Users::class);
        $user->method('getName')->willReturn('Test');
        $user->method('getEmail')->willReturn('test@test.com');
        $this->security->method('getUser')->willReturn($user);
        $this->getCart->method('handler')->willReturn($this->makeCartWithItems());

        [$country, $region, $city] = $this->makeGeo();
        $country->method('getName')->willReturn('Colombia');
        $this->countriesRepository->method('find')->willReturn($country);
        $this->regionsRepository->method('find')->willReturn($region);
        $this->citiesRepository->method('find')->willReturn($city);

        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn('Pendiente');
        $this->statusRepository->method('findOneBy')->willReturn($status);

        $product = $this->createMock(Products::class);
        $product->method('getStock')->willReturn(3);
        $this->productsRepository->method('find')->willReturn($product);

        $this->lowStockAlert->method('markProductIfLow')->with($product)->willReturn(true);
        $this->lowStockAlert->expects($this->once())->method('notifyProduct')->with($product);
        $this->lowStockAlert->expects($this->never())->method('notifyBlock');

        $domain = $this->createMock(\App\Entity\Tenants\Domains\Domains::class);
        $domain->method('getDomain')->willReturn('localhost');
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertTrue($result['success']);
    }

    public function testHandlerNotifiesLowStockForBlockAfterFlush(): void
    {
        $user = $this->createMock(Users::class);
        $user->method('getName')->willReturn('Test');
        $user->method('getEmail')->willReturn('test@test.com');
        $this->security->method('getUser')->willReturn($user);

        $productId = (string) Uuid::v4();
        $colorId   = (string) Uuid::v4();
        $this->getCart->method('handler')->willReturn([
            'items' => [
                ['cartItemId' => $productId, 'productId' => $productId, 'colorId' => $colorId, 'name' => 'Prod A', 'quantity' => 1, 'publicPrice' => 5000, 'itemTotal' => 5000],
            ],
            'subtotal' => 5000,
        ]);

        [$country, $region, $city] = $this->makeGeo();
        $country->method('getName')->willReturn('Colombia');
        $this->countriesRepository->method('find')->willReturn($country);
        $this->regionsRepository->method('find')->willReturn($region);
        $this->citiesRepository->method('find')->willReturn($city);

        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn('Pendiente');
        $this->statusRepository->method('findOneBy')->willReturn($status);

        $product = $this->createMock(Products::class);
        $product->method('getStock')->willReturn(20);
        $this->productsRepository->method('find')->willReturn($product);

        $productColor = $this->createMock(ProductsColors::class);
        $productColor->method('getStock')->willReturn(1);
        $this->productsColorsRepository->method('find')->willReturn($productColor);

        $this->lowStockAlert->method('markBlockIfLow')->with($productColor)->willReturn(true);
        $this->lowStockAlert->expects($this->once())->method('notifyBlock')->with($productColor);
        $this->lowStockAlert->expects($this->never())->method('notifyProduct');

        $domain = $this->createMock(\App\Entity\Tenants\Domains\Domains::class);
        $domain->method('getDomain')->willReturn('localhost');
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertTrue($result['success']);
    }

    public function testHandlerRejectsWhenPaymentMethodNotAvailableForShippingLocation(): void
    {
        $user = $this->createMock(Users::class);
        $this->security->method('getUser')->willReturn($user);
        $this->getCart->method('handler')->willReturn($this->makeCartWithItems());

        [$country, $region, $city] = $this->makeGeo();
        $this->countriesRepository->method('find')->willReturn($country);
        $this->regionsRepository->method('find')->willReturn($region);
        $this->citiesRepository->method('find')->willReturn($city);

        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn('Pendiente');
        $this->statusRepository->method('findOneBy')->willReturn($status);

        $paymentMethod = $this->createMock(PaymentMethods::class);
        $this->paymentMethodsRepository->method('find')->willReturn($paymentMethod);
        $this->paymentMethodAvailability->method('isAvailable')->willReturn(false);

        $this->em->expects($this->never())->method('flush');
        $this->clearCart->expects($this->never())->method('handler');

        $data = $this->makeValidData();
        $data['paymentMethodId'] = (string) Uuid::v4();

        $result = $this->useCase->handler($data);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no está disponible', $result['message']);
    }
}
