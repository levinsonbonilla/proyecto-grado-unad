<?php

namespace App\Tests\Unit\Handler\UseCase\Store\Checkout;

use App\Entity\Configurations\Globals\Countries;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Handler\UseCase\Store\Checkout\PrepareCheckoutUseCase;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Cart\GetCartInterface;
use App\Repository\Configurations\Countries\CountriesDomainsRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Service\Geo\GeoLocation;
use App\Service\Payments\PaymentMethodAvailabilityResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

class PrepareCheckoutUseCaseTest extends TestCase
{
    private Security&MockObject $security;
    private GetCartInterface&MockObject $getCart;
    private GetDomainDataInterface&MockObject $getDomainData;
    private CountriesRepository&MockObject $countriesRepository;
    private CountriesDomainsRepository&MockObject $countriesDomainsRepository;
    private PaymentMethodAvailabilityResolverInterface&MockObject $paymentMethodAvailability;
    private RequestStack&MockObject $requestStack;
    private LogInterface&MockObject $log;
    private PrepareCheckoutUseCase $useCase;

    protected function setUp(): void
    {
        $domain = $this->createMock(Domains::class);

        $this->security      = $this->createMock(Security::class);
        $this->getCart       = $this->createMock(GetCartInterface::class);
        $this->getDomainData = $this->createMock(GetDomainDataInterface::class);
        $this->getDomainData->method('getDomainCache')->willReturn($domain);
        $this->countriesRepository       = $this->createMock(CountriesRepository::class);
        $this->countriesDomainsRepository = $this->createMock(CountriesDomainsRepository::class);
        $this->countriesDomainsRepository->method('getSelectedIds')->willReturn([]);
        $this->paymentMethodAvailability = $this->createMock(PaymentMethodAvailabilityResolverInterface::class);
        $this->paymentMethodAvailability->method('resolve')->willReturn([]);

        $request = $this->createMock(Request::class);
        $request->method('getLocale')->willReturn('es');
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->log = $this->createMock(LogInterface::class);

        $this->useCase = new PrepareCheckoutUseCase(
            $this->security,
            $this->getCart,
            $this->getDomainData,
            $this->countriesRepository,
            $this->countriesDomainsRepository,
            $this->paymentMethodAvailability,
            $this->requestStack,
            $this->log,
        );
    }

    private function nonEmptyCart(): array
    {
        return [
            'items'    => [['productId' => 'abc', 'name' => 'Prod', 'publicPrice' => 1000, 'quantity' => 1, 'itemTotal' => 1000]],
            'subtotal' => 1000,
            'count'    => 1,
        ];
    }

    public function testHandlerReturnsRedirectWhenCartIsEmpty(): void
    {
        $this->getCart->method('handler')->willReturn(['items' => [], 'subtotal' => 0, 'count' => 0]);

        $result = $this->useCase->handler();
        $this->assertArrayHasKey('redirect', $result);
        $this->assertSame('cart', $result['redirect']);
    }

    public function testHandlerReturnsDataWithNonEmptyCart(): void
    {
        $this->getCart->method('handler')->willReturn($this->nonEmptyCart());
        $this->security->method('getUser')->willReturn(null);
        $this->countriesRepository->method('findAllActive')->willReturn([]);

        $result = $this->useCase->handler();

        $this->assertArrayHasKey('cart', $result);
        $this->assertArrayHasKey('countries', $result);
        $this->assertArrayHasKey('paymentMethods', $result);
        $this->assertArrayHasKey('prefill', $result);
    }

    public function testHandlerReturnsErrorOnException(): void
    {
        $this->getCart->method('handler')->willThrowException(new \RuntimeException('DB error'));
        $this->log->expects($this->once())->method('handler');

        $result = $this->useCase->handler();
        $this->assertArrayHasKey('error', $result);
    }

    public function testHandlerUsesRequestLocaleForCountryNames(): void
    {
        $this->getCart->method('handler')->willReturn($this->nonEmptyCart());
        $this->security->method('getUser')->willReturn(null);

        $country = $this->createMock(Countries::class);
        $country->expects($this->atLeastOnce())->method('getName')->with('es')->willReturn('Colombia');
        $country->method('getId')->willReturn(Uuid::v4());
        $this->countriesRepository->method('findAllActive')->willReturn([$country]);

        $result = $this->useCase->handler();

        $this->assertSame('Colombia', $result['countries'][0]['name']);
    }

    public function testHandlerRestrictsCountriesToDomainConfiguredListWhenPresent(): void
    {
        $this->getCart->method('handler')->willReturn($this->nonEmptyCart());
        $this->security->method('getUser')->willReturn(null);

        $allowedId = (string) Uuid::v4();
        $allowed = $this->createMock(Countries::class);
        $allowed->method('getId')->willReturn(Uuid::fromString($allowedId));
        $allowed->method('getName')->willReturn('Colombia');

        $notAllowed = $this->createMock(Countries::class);
        $notAllowed->method('getId')->willReturn(Uuid::v4());
        $notAllowed->method('getName')->willReturn('México');

        $this->countriesRepository->method('findAllActive')->willReturn([$allowed, $notAllowed]);
        $this->countriesDomainsRepository = $this->createMock(CountriesDomainsRepository::class);
        $this->countriesDomainsRepository->method('getSelectedIds')->willReturn([$allowedId]);

        $useCase = new PrepareCheckoutUseCase(
            $this->security,
            $this->getCart,
            $this->getDomainData,
            $this->countriesRepository,
            $this->countriesDomainsRepository,
            $this->paymentMethodAvailability,
            $this->requestStack,
            $this->log,
        );

        $result = $useCase->handler();

        $this->assertCount(1, $result['countries']);
        $this->assertSame('Colombia', $result['countries'][0]['name']);
    }

    public function testHandlerWithoutDomainCountryRestrictionKeepsFullList(): void
    {
        $this->getCart->method('handler')->willReturn($this->nonEmptyCart());
        $this->security->method('getUser')->willReturn(null);

        $a = $this->createMock(Countries::class);
        $a->method('getId')->willReturn(Uuid::v4());
        $a->method('getName')->willReturn('Colombia');
        $b = $this->createMock(Countries::class);
        $b->method('getId')->willReturn(Uuid::v4());
        $b->method('getName')->willReturn('México');

        $this->countriesRepository->method('findAllActive')->willReturn([$a, $b]);

        $result = $this->useCase->handler();

        $this->assertCount(2, $result['countries']);
    }

    public function testHandlerResolvesPaymentMethodsWithUserLocation(): void
    {
        $this->getCart->method('handler')->willReturn($this->nonEmptyCart());

        $userCountry = $this->createMock(Countries::class);
        $userCountry->method('getId')->willReturn(Uuid::v4());
        $userCountry->method('getName')->willReturn('Colombia');

        $user = $this->createMock(Users::class);
        $user->method('getCountry')->willReturn($userCountry);
        $user->method('getRegion')->willReturn(null);
        $user->method('getCity')->willReturn(null);
        $user->method('getAddress')->willReturn('');
        $this->security->method('getUser')->willReturn($user);
        $this->countriesRepository->method('findAllActive')->willReturn([]);

        $this->paymentMethodAvailability = $this->createMock(PaymentMethodAvailabilityResolverInterface::class);
        $this->paymentMethodAvailability->expects($this->once())
            ->method('resolve')
            ->with($this->anything(), $this->callback(fn (GeoLocation $geo) => $geo->country === $userCountry))
            ->willReturn([]);

        $useCase = new PrepareCheckoutUseCase(
            $this->security,
            $this->getCart,
            $this->getDomainData,
            $this->countriesRepository,
            $this->countriesDomainsRepository,
            $this->paymentMethodAvailability,
            $this->requestStack,
            $this->log,
        );

        $useCase->handler();
    }
}
