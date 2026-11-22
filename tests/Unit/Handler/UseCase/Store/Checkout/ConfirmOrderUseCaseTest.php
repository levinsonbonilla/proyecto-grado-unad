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
use App\Service\Products\ProductStockInterface;
use App\Entity\Tenants\Domains\Domains;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
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
    private ProductStockInterface&MockObject $productStock;
    private EntityManagerInterface&MockObject $entityManager;
    private Connection&MockObject $connection;
    private Domains&MockObject $domain;
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
                    'payment_method_required' => 'Selecciona un método de pago para confirmar el pedido.',
                    'payment_method_invalid' => 'El método de pago seleccionado no es válido para esta tienda.',
                    'order_processing_error_ref' => 'Error al procesar el pedido. Ref: ' . ($parameters['%ref%'] ?? ''),
                    default => $id,
                };
            }
        );

        $this->productStock = $this->createMock(ProductStockInterface::class);
        $this->productStock->method('available')->willReturnCallback(
            fn (Products $product, ?ProductsColors $block = null) => $block !== null ? $block->getStock() : $product->getStock()
        );
        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('isTransactionActive')->willReturn(true);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $this->domain = $this->createMock(Domains::class);
        $this->domain->method('getId')->willReturn(Uuid::v4());
        $this->domain->method('getDomain')->willReturn('localhost');
        $this->getDomainData->method('getDomainCache')->willReturn($this->domain);

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
            $this->productStock,
            $this->entityManager,
        );
    }

    private function makeValidData(): array
    {
        return [
            'address'         => 'Calle 123',
            'countryId'       => (string) Uuid::v4(),
            'regionId'        => (string) Uuid::v4(),
            'cityId'          => (string) Uuid::v4(),
            'paymentMethodId' => (string) Uuid::v4(),
        ];
    }

    private function makeCartWithItems(int $quantity = 1, ?string $colorId = null): array
    {
        $productId = (string) Uuid::v4();
        $item = ['cartItemId' => $productId, 'productId' => $productId, 'name' => 'Prod A', 'quantity' => $quantity, 'publicPrice' => 5000, 'itemTotal' => 5000 * $quantity];
        if ($colorId !== null) {
            $item['colorId'] = $colorId;
        }
        return ['items' => [$item], 'subtotal' => 5000 * $quantity];
    }

    private function makeGeo(): array
    {
        $country = $this->createMock(Countries::class);
        $region  = $this->createMock(Regions::class);
        $city    = $this->createMock(Cities::class);
        return [$country, $region, $city];
    }

    private function givenAuthenticatedUserWithCart(array $cart): void
    {
        $user = $this->createMock(Users::class);
        $user->method('getName')->willReturn('Test');
        $user->method('getEmail')->willReturn('test@test.com');
        $this->security->method('getUser')->willReturn($user);
        $this->getCart->method('handler')->willReturn($cart);
    }

    private function givenValidGeoAndStatus(): void
    {
        [$country, $region, $city] = $this->makeGeo();
        $country->method('getName')->willReturn('Colombia');
        $this->countriesRepository->method('find')->willReturn($country);
        $this->regionsRepository->method('find')->willReturn($region);
        $this->citiesRepository->method('find')->willReturn($city);

        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn('Pendiente');
        $this->statusRepository->method('findOneBy')->willReturn($status);
    }

    private function givenPaymentMethod(string $provider = PaymentMethods::PROVIDER_MANUAL, bool $active = true, ?Domains $owner = null, bool $available = true): PaymentMethods&MockObject
    {
        $paymentMethod = $this->createMock(PaymentMethods::class);
        $paymentMethod->method('getProvider')->willReturn($provider);
        $paymentMethod->method('isActive')->willReturn($active);
        $paymentMethod->method('getDomain')->willReturn($owner ?? $this->domain);
        $this->paymentMethodsRepository->method('find')->willReturn($paymentMethod);
        $this->paymentMethodAvailability->method('isAvailable')->willReturn($available);
        return $paymentMethod;
    }

    private function givenProduct(?int $stock): Products&MockObject
    {
        $product = $this->createMock(Products::class);
        $product->method('getName')->willReturn('Prod A');
        $product->method('getStock')->willReturn($stock);
        $this->productsRepository->method('find')->willReturn($product);
        return $product;
    }

    public function testHandlerReturnsFalseWhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertFalse($result['success']);
    }

    public function testHandlerReturnsFalseWhenCartIsEmpty(): void
    {
        $this->givenAuthenticatedUserWithCart(['items' => [], 'subtotal' => 0]);

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('vacío', $result['message']);
    }

    public function testHandlerReturnsFalseWhenAddressIsIncomplete(): void
    {
        $this->givenAuthenticatedUserWithCart($this->makeCartWithItems());

        $result = $this->useCase->handler(['address' => '', 'countryId' => '', 'regionId' => '', 'cityId' => '']);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('envío', $result['message']);
    }

    public function testHandlerReturnsFalseWhenLocationNotFound(): void
    {
        $this->givenAuthenticatedUserWithCart($this->makeCartWithItems());
        $this->countriesRepository->method('find')->willReturn(null);

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Ubicación', $result['message']);
    }

    public function testHandlerReturnsFalseWhenStatusNotFound(): void
    {
        $this->givenAuthenticatedUserWithCart($this->makeCartWithItems());
        [$country, $region, $city] = $this->makeGeo();
        $this->countriesRepository->method('find')->willReturn($country);
        $this->regionsRepository->method('find')->willReturn($region);
        $this->citiesRepository->method('find')->willReturn($city);
        $this->statusRepository->method('findOneBy')->willReturn(null);
        $this->log->method('handler')->willReturn(null);

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertFalse($result['success']);
    }

    public function testHandlerRejectsWhenPaymentMethodIsMissing(): void
    {
        $this->givenAuthenticatedUserWithCart($this->makeCartWithItems());
        $this->givenValidGeoAndStatus();
        $this->givenProduct(5);
        $this->productStock->expects($this->never())->method('reserve');
        $this->em->expects($this->never())->method('flush');

        $data = $this->makeValidData();
        unset($data['paymentMethodId']);
        $result = $this->useCase->handler($data);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('método de pago', $result['message']);
    }

    public function testHandlerRejectsInactivePaymentMethod(): void
    {
        $this->givenAuthenticatedUserWithCart($this->makeCartWithItems());
        $this->givenValidGeoAndStatus();
        $this->givenProduct(5);
        $this->givenPaymentMethod(active: false);
        $this->productStock->expects($this->never())->method('reserve');

        $result = $this->useCase->handler($this->makeValidData());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no es válido', $result['message']);
    }

    public function testHandlerRejectsPaymentMethodOfAnotherDomain(): void
    {
        $this->givenAuthenticatedUserWithCart($this->makeCartWithItems());
        $this->givenValidGeoAndStatus();
        $this->givenProduct(5);
        $foreignDomain = $this->createMock(Domains::class);
        $foreignDomain->method('getId')->willReturn(Uuid::v4());
        $this->givenPaymentMethod(owner: $foreignDomain);
        $this->productStock->expects($this->never())->method('reserve');

        $result = $this->useCase->handler($this->makeValidData());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no es válido', $result['message']);
    }

    public function testHandlerCreatesOrderSuccessfully(): void
    {
        $this->givenAuthenticatedUserWithCart($this->makeCartWithItems());
        $this->givenValidGeoAndStatus();
        $this->givenProduct(null);
        $this->givenPaymentMethod();
        $this->productStock->method('reserve')->willReturn(true);

        $this->em->expects($this->atLeastOnce())->method('add');
        $this->em->expects($this->atLeastOnce())->method('flush');
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->clearCart->expects($this->once())->method('handler');

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('orderId', $result);
    }

    public function testHandlerRedirectsToGatewayWhenPaymentMethodIsMockGateway(): void
    {
        $this->givenAuthenticatedUserWithCart($this->makeCartWithItems());
        $this->givenValidGeoAndStatus();
        $this->givenProduct(null);
        $this->givenPaymentMethod(PaymentMethods::PROVIDER_MOCK_GATEWAY);
        $this->productStock->method('reserve')->willReturn(true);

        $request = $this->createMock(\Symfony\Component\HttpFoundation\Request::class);
        $request->method('getLocale')->willReturn('es');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        $this->urlGenerator->method('generate')->willReturn('https://proyecto-grado-unad.test/es/checkout/return/x');

        $this->paymentGatewayClient->expects($this->once())
            ->method('createIntent')
            ->willReturn(new PaymentIntentResult('pi_123', 'https://gateway.test/pay/pi_123', 'requires_action'));

        $this->mailer->expects($this->never())->method('send');
        $this->clearCart->expects($this->once())->method('handler');

        $result = $this->useCase->handler($this->makeValidData());

        $this->assertTrue($result['success']);
        $this->assertTrue($result['requiresPayment']);
        $this->assertSame('https://gateway.test/pay/pi_123', $result['redirectUrl']);
    }

    public function testHandlerRejectsWhenStockInsufficientForTrackedProduct(): void
    {
        $this->givenAuthenticatedUserWithCart($this->makeCartWithItems(5));
        $this->givenValidGeoAndStatus();
        $this->givenProduct(2);
        $this->givenPaymentMethod();
        $this->productStock->method('currentAvailable')->willReturn(2);
        $this->productStock->expects($this->never())->method('reserve');

        $this->em->expects($this->never())->method('flush');
        $this->clearCart->expects($this->never())->method('handler');

        $result = $this->useCase->handler($this->makeValidData());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('stock', $result['message']);
        $this->assertStringContainsString('Prod A', $result['message']);
        $this->assertStringContainsString('Disponible: 2', $result['message']);
    }

    public function testHandlerRollsBackWhenAtomicReservationLosesTheRace(): void
    {
        $this->givenAuthenticatedUserWithCart($this->makeCartWithItems(3));
        $this->givenValidGeoAndStatus();
        $this->givenProduct(5);
        $this->givenPaymentMethod();
        $this->productStock->method('reserve')->willReturn(false);
        $this->productStock->method('currentAvailable')->willReturn(0);

        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');
        $this->em->expects($this->never())->method('flush');
        $this->clearCart->expects($this->never())->method('handler');

        $result = $this->useCase->handler($this->makeValidData());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Disponible: 0, solicitado: 3', $result['message']);
    }

    public function testHandlerCreatesOrderWhenProductStockIsNull(): void
    {
        $this->givenAuthenticatedUserWithCart($this->makeCartWithItems());
        $this->givenValidGeoAndStatus();
        $product = $this->givenProduct(null);
        $this->givenPaymentMethod();
        $this->productStock->expects($this->once())->method('reserve')->with($product, null, 1)->willReturn(true);

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertTrue($result['success']);
    }

    public function testHandlerReservesStockOnSuccessfulConfirmation(): void
    {
        $this->givenAuthenticatedUserWithCart($this->makeCartWithItems(2));
        $this->givenValidGeoAndStatus();
        $product = $this->givenProduct(5);
        $this->givenPaymentMethod();
        $product->expects($this->never())->method('decreaseStock');
        $this->productStock->expects($this->once())->method('reserve')->with($product, null, 2)->willReturn(true);

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertTrue($result['success']);
    }

    public function testHandlerReservesBlockAndGeneralStockWhenColorSelected(): void
    {
        $colorId = (string) Uuid::v4();
        $this->givenAuthenticatedUserWithCart($this->makeCartWithItems(2, $colorId));
        $this->givenValidGeoAndStatus();
        $product = $this->givenProduct(20);
        $this->givenPaymentMethod();

        $productColor = $this->createMock(ProductsColors::class);
        $productColor->method('getStock')->willReturn(10);
        $this->productsColorsRepository->method('find')->willReturn($productColor);

        $this->productStock->expects($this->once())->method('reserve')->with($product, $productColor, 2)->willReturn(true);

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertTrue($result['success']);
    }

    public function testHandlerNotifiesLowStockForProductAfterCommit(): void
    {
        $this->givenAuthenticatedUserWithCart($this->makeCartWithItems());
        $this->givenValidGeoAndStatus();
        $product = $this->givenProduct(3);
        $this->givenPaymentMethod();
        $this->productStock->method('reserve')->willReturn(true);

        $this->entityManager->expects($this->atLeastOnce())->method('refresh')->with($product);
        $this->lowStockAlert->method('markProductIfLow')->with($product)->willReturn(true);
        $this->lowStockAlert->expects($this->once())->method('notifyProduct')->with($product);
        $this->lowStockAlert->expects($this->never())->method('notifyBlock');

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertTrue($result['success']);
    }

    public function testHandlerNotifiesLowStockForBlockAfterCommit(): void
    {
        $this->givenAuthenticatedUserWithCart($this->makeCartWithItems(1, (string) Uuid::v4()));
        $this->givenValidGeoAndStatus();
        $this->givenProduct(20);
        $this->givenPaymentMethod();
        $this->productStock->method('reserve')->willReturn(true);

        $productColor = $this->createMock(ProductsColors::class);
        $productColor->method('getStock')->willReturn(1);
        $this->productsColorsRepository->method('find')->willReturn($productColor);

        $this->lowStockAlert->method('markBlockIfLow')->with($productColor)->willReturn(true);
        $this->lowStockAlert->expects($this->once())->method('notifyBlock')->with($productColor);
        $this->lowStockAlert->expects($this->never())->method('notifyProduct');

        $result = $this->useCase->handler($this->makeValidData());
        $this->assertTrue($result['success']);
    }

    public function testHandlerRejectsWhenPaymentMethodNotAvailableForShippingLocation(): void
    {
        $this->givenAuthenticatedUserWithCart($this->makeCartWithItems());
        $this->givenValidGeoAndStatus();
        $this->givenProduct(5);
        $this->givenPaymentMethod(available: false);

        $this->productStock->expects($this->never())->method('reserve');
        $this->em->expects($this->never())->method('flush');
        $this->clearCart->expects($this->never())->method('handler');

        $result = $this->useCase->handler($this->makeValidData());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no está disponible', $result['message']);
    }
}
