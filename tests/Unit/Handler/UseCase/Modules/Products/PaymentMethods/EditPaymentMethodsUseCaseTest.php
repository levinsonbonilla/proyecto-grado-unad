<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Products\PaymentMethods;

use App\Entity\Configurations\Countries\CountriesPaymentMethods;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Handler\UseCase\Modules\Products\PaymentMethods\EditPaymentMethodsUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Configurations\Cities\CitiesPaymentMethodsRepository;
use App\Repository\Configurations\Countries\CountriesPaymentMethodsRepository;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Repository\Configurations\Regions\RegionsPaymentMethodsRepository;
use App\Repository\Tenants\Domains\PaymentMethodsRepository;
use App\ReturnHandler\FormReturn;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class EditPaymentMethodsUseCaseTest extends TestCase
{
    private FormFactoryInterface&MockObject $formFactory;
    private FormInterface&MockObject $form;
    private FormInterface&MockObject $childForm;
    private RequestStack&MockObject $requestStack;
    private Request&MockObject $request;
    private LogInterface&MockObject $log;
    private TranslatorInterface&MockObject $translator;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private PaymentMethodsRepository&MockObject $paymentMethodsRepository;
    private CountriesPaymentMethodsRepository&MockObject $countriesJunctionRepo;
    private RegionsPaymentMethodsRepository&MockObject $regionsJunctionRepo;
    private CitiesPaymentMethodsRepository&MockObject $citiesJunctionRepo;
    private PaymentMethods&MockObject $entity;
    private EditPaymentMethodsUseCase $useCase;

    protected function setUp(): void
    {
        $this->childForm = $this->createMock(FormInterface::class);
        $this->childForm->method('setData')->willReturnSelf();

        $this->form = $this->createMock(FormInterface::class);
        $this->form->method('handleRequest')->willReturnSelf();
        $this->form->method('add')->willReturnSelf();
        $this->form->method('get')->willReturn($this->childForm);

        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->formFactory->method('create')->willReturn($this->form);

        $this->request      = $this->createMock(Request::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($this->request);

        $this->log                     = $this->createMock(LogInterface::class);
        $this->translator              = $this->createMock(TranslatorInterface::class);
        $this->entityManager           = $this->createMock(CustomeEntityManagerInterface::class);
        $this->paymentMethodsRepository = $this->createMock(PaymentMethodsRepository::class);

        $this->entity = $this->createMock(PaymentMethods::class);

        $this->countriesJunctionRepo = $this->createMock(CountriesPaymentMethodsRepository::class);
        $this->countriesJunctionRepo->method('findAllByPaymentMethod')->willReturn([]);
        $this->regionsJunctionRepo = $this->createMock(RegionsPaymentMethodsRepository::class);
        $this->regionsJunctionRepo->method('findAllByPaymentMethod')->willReturn([]);
        $this->citiesJunctionRepo = $this->createMock(CitiesPaymentMethodsRepository::class);
        $this->citiesJunctionRepo->method('findAllByPaymentMethod')->willReturn([]);

        $this->useCase = new EditPaymentMethodsUseCase(
            $this->requestStack,
            $this->formFactory,
            $this->log,
            $this->translator,
            $this->entityManager,
            $this->paymentMethodsRepository,
            $this->countriesJunctionRepo,
            $this->regionsJunctionRepo,
            $this->citiesJunctionRepo,
            $this->createMock(CountriesRepository::class),
            $this->createMock(RegionsRepository::class),
            $this->createMock(CitiesRepository::class),
        );
    }

    private function stubEntityGetters(): void
    {
        $this->entity->method('getName')->willReturn('Transferencia bancaria');
        $this->entity->method('getId')->willReturn(Uuid::v4());
        $this->entity->method('getProvider')->willReturn(PaymentMethods::PROVIDER_MANUAL);
        $this->entity->method('getInstructions')->willReturn(null);
    }

    public function testHandlerReturnsFormReturnInstance(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->stubEntityGetters();

        $result = $this->useCase->handler($this->entity);

        $this->assertInstanceOf(FormReturn::class, $result);
    }

    public function testHandlerOnGetPopulatesFormWithEntityData(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->stubEntityGetters();

        $this->childForm
            ->expects($this->atLeastOnce())
            ->method('setData');

        $result = $this->useCase->handler($this->entity);

        $this->assertFalse($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerWithValidSubmissionEditsPaymentMethod(): void
    {
        $editData = ['name' => 'Efectivo'];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);

        $this->request->method('get')
            ->with('payment_methods', [])
            ->willReturn($editData);

        $this->stubEntityGetters();
        $this->entity->method('edit')->willReturnSelf();

        $this->paymentMethodsRepository
            ->expects($this->once())
            ->method('invalidateCache');

        $this->entityManager
            ->expects($this->once())
            ->method('add');

        $this->translator->method('trans')->willReturn('Método de pago editado');

        $result = $this->useCase->handler($this->entity);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
        $this->assertSame('Método de pago editado', $result->getMessage());
    }

    public function testHandlerWithSubmittedInvalidFormDoesNotPersist(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(false);

        $this->entityManager
            ->expects($this->never())
            ->method('add');

        $result = $this->useCase->handler($this->entity);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerCatchesExceptionAndReturnsError(): void
    {
        $editData = ['name' => 'Test'];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);

        $this->request->method('get')
            ->with('payment_methods', [])
            ->willReturn($editData);

        $this->entity->method('edit')
            ->willThrowException(new \RuntimeException('Error inesperado'));
        $this->entity->method('getId')->willReturn(Uuid::v4());

        $this->log->method('handler')->willReturn(null);
        $this->translator->method('trans')->willReturn('Error al editar');

        $result = $this->useCase->handler($this->entity);

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }

    public function testHandlerInvalidatesAllJunctionCachesOnValidSubmission(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')
            ->with('payment_methods', [])
            ->willReturn(['name' => 'Efectivo']);

        $this->stubEntityGetters();
        $this->entity->method('edit')->willReturnSelf();
        $this->translator->method('trans')->willReturn('Editado');

        $this->countriesJunctionRepo->expects($this->once())->method('invalidateSelectedIdsCache');
        $this->regionsJunctionRepo->expects($this->once())->method('invalidateSelectedIdsCache');
        $this->citiesJunctionRepo->expects($this->once())->method('invalidateSelectedIdsCache');

        $result = $this->useCase->handler($this->entity);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testSyncActivatesExistingGeoLinkWhenIdIsIncoming(): void
    {
        $countryId = 'e9f5a1b2-c3d4-4e5f-a6b7-c8d9e0f1a2b3';

        $country = $this->createMock(Countries::class);
        $country->method('getId')->willReturn(Uuid::fromString($countryId));

        $existingLink = $this->createMock(CountriesPaymentMethods::class);
        $existingLink->method('getCountry')->willReturn($country);
        $existingLink->expects($this->once())->method('activate');
        $existingLink->expects($this->never())->method('deactivate');

        $countriesJunctionRepo = $this->createMock(CountriesPaymentMethodsRepository::class);
        $countriesJunctionRepo->method('findAllByPaymentMethod')->willReturn([$existingLink]);
        $regionsJunctionRepo = $this->createMock(RegionsPaymentMethodsRepository::class);
        $regionsJunctionRepo->method('findAllByPaymentMethod')->willReturn([]);
        $citiesJunctionRepo = $this->createMock(CitiesPaymentMethodsRepository::class);
        $citiesJunctionRepo->method('findAllByPaymentMethod')->willReturn([]);

        $useCase = new EditPaymentMethodsUseCase(
            $this->requestStack, $this->formFactory, $this->log, $this->translator,
            $this->entityManager, $this->paymentMethodsRepository,
            $countriesJunctionRepo, $regionsJunctionRepo, $citiesJunctionRepo,
            $this->createMock(CountriesRepository::class),
            $this->createMock(RegionsRepository::class),
            $this->createMock(CitiesRepository::class),
        );

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')
            ->with('payment_methods', [])
            ->willReturn(['name' => 'Efectivo', 'countries' => [$countryId]]);
        $this->entity->method('edit')->willReturnSelf();
        $this->entity->method('getId')->willReturn(Uuid::v4());
        $this->translator->method('trans')->willReturn('Editado');

        $useCase->handler($this->entity);
    }

    public function testSyncDeactivatesRemovedGeoLinkWhenIdNotIncoming(): void
    {
        $countryId = 'e9f5a1b2-c3d4-4e5f-a6b7-c8d9e0f1a2b3';

        $country = $this->createMock(Countries::class);
        $country->method('getId')->willReturn(Uuid::fromString($countryId));

        $existingLink = $this->createMock(CountriesPaymentMethods::class);
        $existingLink->method('getCountry')->willReturn($country);
        $existingLink->expects($this->once())->method('deactivate');
        $existingLink->expects($this->never())->method('activate');

        $countriesJunctionRepo = $this->createMock(CountriesPaymentMethodsRepository::class);
        $countriesJunctionRepo->method('findAllByPaymentMethod')->willReturn([$existingLink]);
        $regionsJunctionRepo = $this->createMock(RegionsPaymentMethodsRepository::class);
        $regionsJunctionRepo->method('findAllByPaymentMethod')->willReturn([]);
        $citiesJunctionRepo = $this->createMock(CitiesPaymentMethodsRepository::class);
        $citiesJunctionRepo->method('findAllByPaymentMethod')->willReturn([]);

        $useCase = new EditPaymentMethodsUseCase(
            $this->requestStack, $this->formFactory, $this->log, $this->translator,
            $this->entityManager, $this->paymentMethodsRepository,
            $countriesJunctionRepo, $regionsJunctionRepo, $citiesJunctionRepo,
            $this->createMock(CountriesRepository::class),
            $this->createMock(RegionsRepository::class),
            $this->createMock(CitiesRepository::class),
        );

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')
            ->with('payment_methods', [])
            ->willReturn(['name' => 'Efectivo']);
        $this->entity->method('edit')->willReturnSelf();
        $this->entity->method('getId')->willReturn(Uuid::v4());
        $this->translator->method('trans')->willReturn('Editado');

        $useCase->handler($this->entity);
    }

    public function testSyncCreatesNewJunctionEntityForNewGeoId(): void
    {
        $countriesJunctionRepo = $this->createMock(CountriesPaymentMethodsRepository::class);
        $countriesJunctionRepo->method('findAllByPaymentMethod')->willReturn([]);
        $regionsJunctionRepo = $this->createMock(RegionsPaymentMethodsRepository::class);
        $regionsJunctionRepo->method('findAllByPaymentMethod')->willReturn([]);
        $citiesJunctionRepo = $this->createMock(CitiesPaymentMethodsRepository::class);
        $citiesJunctionRepo->method('findAllByPaymentMethod')->willReturn([]);

        $countriesRepo = $this->createMock(CountriesRepository::class);
        $countriesRepo->method('find')->willReturn($this->createMock(Countries::class));

        $useCase = new EditPaymentMethodsUseCase(
            $this->requestStack, $this->formFactory, $this->log, $this->translator,
            $this->entityManager, $this->paymentMethodsRepository,
            $countriesJunctionRepo, $regionsJunctionRepo, $citiesJunctionRepo,
            $countriesRepo,
            $this->createMock(RegionsRepository::class),
            $this->createMock(CitiesRepository::class),
        );

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')
            ->with('payment_methods', [])
            ->willReturn(['name' => 'Efectivo', 'countries' => ['new-country-uuid']]);
        $this->entity->method('edit')->willReturnSelf();
        $this->entity->method('getId')->willReturn(Uuid::v4());
        $this->translator->method('trans')->willReturn('Editado');

        $this->entityManager->expects($this->atLeast(2))->method('add');

        $useCase->handler($this->entity);
    }
}
