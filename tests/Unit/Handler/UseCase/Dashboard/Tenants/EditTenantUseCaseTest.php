<?php

namespace App\Tests\Unit\Handler\UseCase\Dashboard\Tenants;

use App\Entity\Tenants\Tenants;
use App\Handler\UseCase\Dashboard\Tenants\EditTenantUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\TenantsRepository;
use App\ReturnHandler\FormReturn;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class EditTenantUseCaseTest extends TestCase
{
    private FormFactoryInterface&MockObject $formFactory;
    private FormInterface&MockObject $form;
    private FormInterface&MockObject $childForm;
    private RequestStack&MockObject $requestStack;
    private Request&MockObject $request;
    private LogInterface&MockObject $log;
    private TenantsRepository&MockObject $tenantsRepository;
    private TranslatorInterface&MockObject $translator;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private Tenants&MockObject $tenant;
    private EditTenantUseCase $useCase;

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

        $this->log               = $this->createMock(LogInterface::class);
        $this->tenantsRepository = $this->createMock(TenantsRepository::class);
        $this->translator        = $this->createMock(TranslatorInterface::class);
        $this->entityManager     = $this->createMock(CustomeEntityManagerInterface::class);

        $this->tenant = $this->createMock(Tenants::class);

        $this->useCase = new EditTenantUseCase(
            $this->requestStack,
            $this->formFactory,
            $this->log,
            $this->translator,
            $this->entityManager,
            $this->tenantsRepository
        );
    }

    private function stubTenantGetters(): void
    {
        $this->tenant->method('getName')->willReturn('Empresa Original');
        $this->tenant->method('getDescription')->willReturn('Descripción Original');
        $this->tenant->method('getNit')->willReturn('900123456');
        $this->tenant->method('getPrefix')->willReturn('+57');
        $this->tenant->method('getPhone')->willReturn('3001234567');
        $this->tenant->method('isActive')->willReturn(true);
        $this->tenant->method('isPrincipal')->willReturn(false);
        $this->tenant->method('getId')->willReturn(Uuid::v4());
    }

    public function testHandlerReturnsFormReturnInstance(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->stubTenantGetters();

        $result = $this->useCase->handler($this->tenant);

        $this->assertInstanceOf(FormReturn::class, $result);
    }

    public function testHandlerOnGetPopulatesFormWithTenantData(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);
        $this->stubTenantGetters();

        $this->childForm
            ->expects($this->atLeastOnce())
            ->method('setData');

        $result = $this->useCase->handler($this->tenant);

        $this->assertFalse($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerWithValidSubmissionEditsTenant(): void
    {
        $editData = [
            'name'        => 'Empresa Editada',
            'description' => 'Nueva descripción',
            'nit'         => '900123456',
            'phone'       => '3009999999',
            'prefix'      => '+57',
            'activated'   => '1',
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);

        $this->request->method('get')
            ->with('tenants', [])
            ->willReturn($editData);

        $this->stubTenantGetters();
        $this->tenant->method('edit')->willReturnSelf();
        $this->tenant->method('activate')->willReturnSelf();

        $this->entityManager
            ->expects($this->once())
            ->method('add');

        $this->tenantsRepository
            ->expects($this->once())
            ->method('invalidateCacheTenantId');

        $this->translator->method('trans')->willReturn('Tenant editado exitosamente');

        $result = $this->useCase->handler($this->tenant);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
        $this->assertSame('Tenant editado exitosamente', $result->getMessage());
    }

    public function testHandlerDeactivatesTenantWhenActivatedIsFalse(): void
    {
        $editData = [
            'name'        => 'Empresa',
            'description' => 'Descripción',
            'nit'         => '900123456',
            'phone'       => '3001234567',
            'prefix'      => '+57',
            'activated'   => false,
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);

        $this->request->method('get')
            ->with('tenants', [])
            ->willReturn($editData);

        $this->stubTenantGetters();
        $this->tenant->method('edit')->willReturnSelf();

        $this->tenant
            ->expects($this->once())
            ->method('deactivate');

        $this->translator->method('trans')->willReturn('Editado');

        $this->useCase->handler($this->tenant);
    }

    public function testHandlerMarksTenantAsPrincipalWhenIsPrincipalIsTrue(): void
    {
        $editData = [
            'name'        => 'Empresa',
            'description' => 'Descripción',
            'nit'         => '900123456',
            'phone'       => '3001234567',
            'prefix'      => '+57',
            'activated'   => '1',
            'isPrincipal' => true,
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);

        $this->request->method('get')
            ->with('tenants', [])
            ->willReturn($editData);

        $this->stubTenantGetters();
        $this->tenant->method('edit')->willReturnSelf();
        $this->tenant->method('activate')->willReturnSelf();

        $this->tenant
            ->expects($this->once())
            ->method('markAsPrincipal');
        $this->tenant
            ->expects($this->never())
            ->method('unmarkAsPrincipal');

        $this->translator->method('trans')->willReturn('Editado');

        $this->useCase->handler($this->tenant);
    }

    public function testHandlerUnmarksTenantAsPrincipalWhenIsPrincipalIsFalse(): void
    {
        $editData = [
            'name'        => 'Empresa',
            'description' => 'Descripción',
            'nit'         => '900123456',
            'phone'       => '3001234567',
            'prefix'      => '+57',
            'activated'   => '1',
            'isPrincipal' => false,
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);

        $this->request->method('get')
            ->with('tenants', [])
            ->willReturn($editData);

        $this->stubTenantGetters();
        $this->tenant->method('edit')->willReturnSelf();
        $this->tenant->method('activate')->willReturnSelf();

        $this->tenant
            ->expects($this->once())
            ->method('unmarkAsPrincipal');
        $this->tenant
            ->expects($this->never())
            ->method('markAsPrincipal');

        $this->translator->method('trans')->willReturn('Editado');

        $this->useCase->handler($this->tenant);
    }

    public function testHandlerCatchesExceptionAndReturnsError(): void
    {
        $editData = [
            'name'        => 'Empresa',
            'description' => 'Descripción',
            'nit'         => '900123456',
            'phone'       => '3001234567',
            'prefix'      => '+57',
            'activated'   => '1',
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);

        $this->request->method('get')
            ->with('tenants', [])
            ->willReturn($editData);

        $this->stubTenantGetters();
        $this->tenant->method('edit')->willThrowException(new \RuntimeException('Error inesperado'));

        $this->log->method('handler')->willReturn(null);
        $this->translator->method('trans')->willReturn('Error al editar');

        $result = $this->useCase->handler($this->tenant);

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }
}
