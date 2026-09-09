<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Products\PaymentMethods;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Entity\Tenants\Domains\Domains;
use App\Interface\Configuration\ActiveDashboardDomainResolverInterface;
use App\Handler\Configuration\GetDomainData;
use App\Handler\UseCase\Modules\Products\PaymentMethods\AddPaymentMethodsUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\Repository\Tenants\TenantsRepository;
use App\ReturnHandler\FormReturn;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class AddPaymentMethodsUseCaseTest extends TestCase
{
    private FormFactoryInterface&MockObject $formFactory;
    private FormInterface&MockObject $form;
    private RequestStack&MockObject $requestStack;
    private Request&MockObject $request;
    private LogInterface&MockObject $log;
    private TranslatorInterface&MockObject $translator;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private GetDomainData $getDomainData;
    private AddPaymentMethodsUseCase $useCase;

    protected function setUp(): void
    {
        $this->form = $this->createMock(FormInterface::class);
        $this->form->method('handleRequest')->willReturnSelf();
        $this->form->method('getName')->willReturn('payment_methods');

        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->formFactory->method('create')->willReturn($this->form);

        $this->request        = $this->createMock(Request::class);
        $this->request->files = new FileBag([]);
        $this->requestStack   = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($this->request);

        $this->log           = $this->createMock(LogInterface::class);
        $this->translator    = $this->createMock(TranslatorInterface::class);
        $this->entityManager = $this->createMock(CustomeEntityManagerInterface::class);

        $domain = $this->createMock(Domains::class);
        $domainsRepository = $this->createMock(DomainsRepository::class);
        $domainsRepository->method('getDomain')->willReturn($domain);

        $domainRequest = $this->createMock(Request::class);
        $domainRequest->method('getSchemeAndHttpHost')->willReturn('http://localhost');
        $domainRequestStack = $this->createMock(RequestStack::class);
        $domainRequestStack->method('getCurrentRequest')->willReturn($domainRequest);

        $this->getDomainData = new GetDomainData(
            $domainRequestStack,
            $domainsRepository,
            $this->createMock(TenantsRepository::class),
            $this->createMock(ActiveDashboardDomainResolverInterface::class)
        );

        $this->useCase = new AddPaymentMethodsUseCase(
            $this->requestStack,
            $this->formFactory,
            $this->log,
            $this->translator,
            $this->entityManager,
            $this->getDomainData,
            $this->createMock(CountriesRepository::class),
            $this->createMock(RegionsRepository::class),
            $this->createMock(CitiesRepository::class),
        );
    }

    public function testHandlerReturnsFormReturnInstance(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);

        $result = $this->useCase->handler(\App\Form\Modules\Products\PaymentMethodsType::class);

        $this->assertInstanceOf(FormReturn::class, $result);
    }

    public function testHandlerWithNoSubmissionDoesNotProcess(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);

        $result = $this->useCase->handler(\App\Form\Modules\Products\PaymentMethodsType::class);

        $this->assertFalse($result->isProcess());
        $this->assertFalse($result->isError());
        $this->assertNull($result->getMessage());
    }

    public function testHandlerWithValidFormCreatesPaymentMethod(): void
    {
        $data = ['name' => 'Tarjeta de crédito'];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);

        $this->request->method('get')
            ->with('payment_methods', [])
            ->willReturn($data);

        $this->entityManager
            ->expects($this->once())
            ->method('add');

        $this->translator->method('trans')->willReturn('Método de pago creado');

        $result = $this->useCase->handler(\App\Form\Modules\Products\PaymentMethodsType::class);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerWithSubmittedInvalidFormDoesNotPersist(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(false);

        $this->entityManager
            ->expects($this->never())
            ->method('add');

        $result = $this->useCase->handler(\App\Form\Modules\Products\PaymentMethodsType::class);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerCatchesUnexpectedException(): void
    {
        $data = ['name' => 'Test'];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);

        $this->request->method('get')
            ->with('payment_methods', [])
            ->willReturn($data);

        $this->entityManager->method('add')
            ->willThrowException(new \RuntimeException('Error de base de datos'));

        $this->log->method('handler')->willReturn(null);
        $this->translator->method('trans')->willReturn('Error de registro');

        $result = $this->useCase->handler(\App\Form\Modules\Products\PaymentMethodsType::class);

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }

    public function testHandlerWithGeoSelectionCreatesJunctionEntities(): void
    {
        $countriesRepo = $this->createMock(CountriesRepository::class);
        $countriesRepo->method('find')->willReturn($this->createMock(Countries::class));
        $regionsRepo = $this->createMock(RegionsRepository::class);
        $regionsRepo->method('find')->willReturn($this->createMock(Regions::class));
        $citiesRepo = $this->createMock(CitiesRepository::class);
        $citiesRepo->method('find')->willReturn($this->createMock(Cities::class));

        $useCase = new AddPaymentMethodsUseCase(
            $this->requestStack,
            $this->formFactory,
            $this->log,
            $this->translator,
            $this->entityManager,
            $this->getDomainData,
            $countriesRepo,
            $regionsRepo,
            $citiesRepo,
        );

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')
            ->with('payment_methods', [])
            ->willReturn([
                'name'      => 'Tarjeta',
                'countries' => ['uuid-country-1'],
                'regions'   => ['uuid-region-1'],
                'cities'    => ['uuid-city-1'],
            ]);

        $this->entityManager->expects($this->atLeast(4))->method('add');
        $this->entityManager->expects($this->once())->method('flush');
        $this->translator->method('trans')->willReturn('Creado');

        $result = $useCase->handler(\App\Form\Modules\Products\PaymentMethodsType::class);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerWithUnknownGeoIdSkipsJunctionCreation(): void
    {
        $countriesRepo = $this->createMock(CountriesRepository::class);
        $countriesRepo->method('find')->willReturn(null);

        $useCase = new AddPaymentMethodsUseCase(
            $this->requestStack,
            $this->formFactory,
            $this->log,
            $this->translator,
            $this->entityManager,
            $this->getDomainData,
            $countriesRepo,
            $this->createMock(RegionsRepository::class),
            $this->createMock(CitiesRepository::class),
        );

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')
            ->with('payment_methods', [])
            ->willReturn(['name' => 'Tarjeta', 'countries' => ['nonexistent-uuid']]);

        $this->entityManager->expects($this->once())->method('add');
        $this->translator->method('trans')->willReturn('Creado');

        $result = $useCase->handler(\App\Form\Modules\Products\PaymentMethodsType::class);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }
}
