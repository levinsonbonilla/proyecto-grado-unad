<?php

namespace App\Tests\Integration\Handler\UseCase\Modules\Products\PaymentMethods;

use App\Entity\Tenants\Domains\PaymentMethods;
use App\Form\Modules\Products\PaymentMethodsType;
use App\Handler\UseCase\Modules\Products\PaymentMethods\AddPaymentMethodsUseCase;
use App\Handler\UseCase\Modules\Products\PaymentMethods\DeletePaymentMethodsUseCase;
use App\Handler\UseCase\Modules\Products\PaymentMethods\EditPaymentMethodsUseCase;
use App\Handler\UseCase\Modules\Products\PaymentMethods\ListPaymentMethodsUseCase;
use App\Interface\UseCase\Modules\Products\PaymentMethods\AddPaymentMethodsInterface;
use App\Interface\UseCase\Modules\Products\PaymentMethods\DeletePaymentMethodsInterface;
use App\Interface\UseCase\Modules\Products\PaymentMethods\EditPaymentMethodsInterface;
use App\Interface\UseCase\Modules\Products\PaymentMethods\ListPaymentMethodsInterface;
use App\Repository\Configurations\Cities\CitiesPaymentMethodsRepository;
use App\Repository\Configurations\Countries\CountriesPaymentMethodsRepository;
use App\Repository\Configurations\Regions\RegionsPaymentMethodsRepository;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\HttpFoundation\Request;

class PaymentMethodsGeoIntegrationTest extends IntegrationTestCase
{

    public function testAllUseCasesAreRegisteredInContainer(): void
    {
        $this->assertInstanceOf(AddPaymentMethodsUseCase::class, static::getContainer()->get(AddPaymentMethodsInterface::class));
        $this->assertInstanceOf(EditPaymentMethodsUseCase::class, static::getContainer()->get(EditPaymentMethodsInterface::class));
        $this->assertInstanceOf(ListPaymentMethodsUseCase::class, static::getContainer()->get(ListPaymentMethodsInterface::class));
        $this->assertInstanceOf(DeletePaymentMethodsUseCase::class, static::getContainer()->get(DeletePaymentMethodsInterface::class));
    }

    public function testFixturePaymentMethodsExistInDatabase(): void
    {
        $repo = $this->em->getRepository(PaymentMethods::class);

        $tarjeta     = $repo->findOneBy(['name' => 'Tarjeta de crédito']);
        $efectivo    = $repo->findOneBy(['name' => 'Efectivo']);
        $transferencia = $repo->findOneBy(['name' => 'Transferencia bancaria']);

        $this->assertNotNull($tarjeta, 'Fixture: "Tarjeta de crédito" debe existir');
        $this->assertNotNull($efectivo, 'Fixture: "Efectivo" debe existir');
        $this->assertNotNull($transferencia, 'Fixture: "Transferencia bancaria" debe existir');
        $this->assertTrue($tarjeta->isActive());
    }

    public function testCountryJunctionFixtureLinksFirstPaymentMethod(): void
    {
        $pm   = $this->em->getRepository(PaymentMethods::class)->findOneBy(['name' => 'Tarjeta de crédito']);
        $repo = static::getContainer()->get(CountriesPaymentMethodsRepository::class);

        $ids = $repo->getSelectedIds($pm, false);

        $this->assertNotEmpty($ids, 'El fixture debe vincular "Tarjeta de crédito" a al menos un país');
    }

    public function testRegionJunctionFixtureLinksFirstPaymentMethod(): void
    {
        $pm   = $this->em->getRepository(PaymentMethods::class)->findOneBy(['name' => 'Tarjeta de crédito']);
        $repo = static::getContainer()->get(RegionsPaymentMethodsRepository::class);

        $ids = $repo->getSelectedIds($pm, false);

        $this->assertNotEmpty($ids, 'El fixture debe vincular "Tarjeta de crédito" a al menos una región');
    }

    public function testCityJunctionFixtureLinksFirstPaymentMethod(): void
    {
        $pm   = $this->em->getRepository(PaymentMethods::class)->findOneBy(['name' => 'Tarjeta de crédito']);
        $repo = static::getContainer()->get(CitiesPaymentMethodsRepository::class);

        $ids = $repo->getSelectedIds($pm, false);

        $this->assertNotEmpty($ids, 'El fixture debe vincular "Tarjeta de crédito" a al menos una ciudad');
    }

    public function testSecondPaymentMethodHasNoGeoJunctions(): void
    {
        $pm              = $this->em->getRepository(PaymentMethods::class)->findOneBy(['name' => 'Efectivo']);
        $countriesRepo   = static::getContainer()->get(CountriesPaymentMethodsRepository::class);
        $regionsRepo     = static::getContainer()->get(RegionsPaymentMethodsRepository::class);
        $citiesRepo      = static::getContainer()->get(CitiesPaymentMethodsRepository::class);

        $this->assertEmpty($countriesRepo->getSelectedIds($pm, false), '"Efectivo" no debe tener países asignados');
        $this->assertEmpty($regionsRepo->getSelectedIds($pm, false), '"Efectivo" no debe tener regiones asignadas');
        $this->assertEmpty($citiesRepo->getSelectedIds($pm, false), '"Efectivo" no debe tener ciudades asignadas');
    }

    public function testGetRequestShowsAddFormWithoutSubmission(): void
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(new Request());

        $service = static::getContainer()->get(AddPaymentMethodsInterface::class);
        $result  = $service->handler(PaymentMethodsType::class);

        $this->assertFalse($result->isProcess());
        $this->assertFalse($result->isError());
        $this->assertNotNull($result->getForm());
    }

    public function testAddCreatesPaymentMethodOnValidPost(): void
    {

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create('http://localhost:8060/new', 'POST', [
            'payment_methods' => ['name' => 'Pago en cuotas'],
        ]));

        $service = static::getContainer()->get(AddPaymentMethodsInterface::class);
        $result  = $service->handler(PaymentMethodsType::class);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());

        $created = $this->em->getRepository(PaymentMethods::class)->findOneBy(['name' => 'Pago en cuotas']);
        $this->assertNotNull($created, 'La entidad debe persistirse en la BD');
        $this->assertTrue($created->isActive());
    }

    public function testGetRequestShowsEditFormWithoutSubmission(): void
    {
        $pm = $this->em->getRepository(PaymentMethods::class)->findOneBy(['name' => 'Efectivo']);

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(new Request());

        $service = static::getContainer()->get(EditPaymentMethodsInterface::class);
        $result  = $service->handler($pm);

        $this->assertFalse($result->isProcess());
        $this->assertNotNull($result->getForm());
    }

    public function testEditUpdatesPaymentMethodNameOnValidPost(): void
    {
        $pm = $this->em->getRepository(PaymentMethods::class)->findOneBy(['name' => 'Efectivo']);

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create('/edit', 'POST', [
            'payment_methods' => ['name' => 'Efectivo actualizado'],
        ]));

        $service = static::getContainer()->get(EditPaymentMethodsInterface::class);
        $result  = $service->handler($pm);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());

        $this->em->refresh($pm);
        $this->assertSame('Efectivo actualizado', $pm->getName());
    }

    public function testEditWithEmptyGeoDeactivatesExistingJunctions(): void
    {
        $pm = $this->em->getRepository(PaymentMethods::class)->findOneBy(['name' => 'Tarjeta de crédito']);

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(Request::create('/edit', 'POST', [
            'payment_methods' => ['name' => 'Tarjeta de crédito', 'countries' => [], 'regions' => [], 'cities' => []],
        ]));

        $service = static::getContainer()->get(EditPaymentMethodsInterface::class);
        $service->handler($pm);

        $countriesRepo = static::getContainer()->get(CountriesPaymentMethodsRepository::class);
        $ids = $countriesRepo->getSelectedIds($pm, false);
        $this->assertEmpty($ids, 'Todos los vínculos de países deben desactivarse');
    }

    public function testDeleteTogglesActiveState(): void
    {
        $pm = $this->em->getRepository(PaymentMethods::class)->findOneBy(['name' => 'Transferencia bancaria']);
        $this->assertTrue($pm->isActive(), 'Precondición: la entidad debe estar activa');

        $service = static::getContainer()->get(DeletePaymentMethodsInterface::class);
        $result  = $service->handler($pm);

        $this->assertTrue($result['success']);
        $this->em->refresh($pm);
        $this->assertFalse($pm->isActive(), 'La entidad debe quedar inactiva tras la primera llamada');
    }

    public function testDeleteActivatesInactivePaymentMethod(): void
    {
        $pm = $this->em->getRepository(PaymentMethods::class)->findOneBy(['name' => 'Transferencia bancaria']);
        $pm->deactivate();
        $this->em->flush();

        $service = static::getContainer()->get(DeletePaymentMethodsInterface::class);
        $result  = $service->handler($pm);

        $this->assertTrue($result['success']);
        $this->em->refresh($pm);
        $this->assertTrue($pm->isActive(), 'La entidad debe quedar activa tras la segunda llamada');
    }
}
