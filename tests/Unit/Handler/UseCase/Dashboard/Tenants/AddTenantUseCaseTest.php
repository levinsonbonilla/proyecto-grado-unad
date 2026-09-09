<?php

namespace App\Tests\Unit\Handler\UseCase\Dashboard\Tenants;

use App\Entity\Tenants\Tenants;
use App\Handler\UseCase\Dashboard\Tenants\AddTenantUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
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

class AddTenantUseCaseTest extends TestCase
{
    private FormFactoryInterface&MockObject $formFactory;
    private FormInterface&MockObject $form;
    private RequestStack&MockObject $requestStack;
    private Request&MockObject $request;
    private LogInterface&MockObject $log;
    private TenantsRepository&MockObject $tenantsRepository;
    private TranslatorInterface&MockObject $translator;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private AddTenantUseCase $useCase;

    protected function setUp(): void
    {
        $this->form = $this->createMock(FormInterface::class);
        $this->form->method('handleRequest')->willReturnSelf();
        $this->form->method('getName')->willReturn('tenants');

        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->formFactory->method('create')->willReturn($this->form);

        $this->request        = $this->createMock(Request::class);
        $this->request->files = new FileBag([]);
        $this->requestStack   = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($this->request);

        $this->log               = $this->createMock(LogInterface::class);
        $this->tenantsRepository = $this->createMock(TenantsRepository::class);
        $this->translator        = $this->createMock(TranslatorInterface::class);
        $this->entityManager     = $this->createMock(CustomeEntityManagerInterface::class);

        $this->useCase = new AddTenantUseCase(
            $this->requestStack,
            $this->formFactory,
            $this->log,
            $this->translator,
            $this->tenantsRepository,
            $this->entityManager
        );
    }

    public function testHandlerReturnsFormReturnInstance(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);

        $result = $this->useCase->handler();

        $this->assertInstanceOf(FormReturn::class, $result);
    }

    public function testHandlerWithNoSubmissionDoesNotProcess(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);

        $result = $this->useCase->handler();

        $this->assertFalse($result->isProcess());
        $this->assertFalse($result->isError());
        $this->assertNull($result->getMessage());
    }

    public function testHandlerWithValidFormCreatesTenant(): void
    {
        $tenantData = [
            'name'        => 'Empresa Test',
            'description' => 'Descripción test',
            'nit'         => '900123456',
            'phone'       => '3001234567',
            'prefix'      => '+57',
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);

        $this->request->method('get')
            ->with('tenants', [])
            ->willReturn($tenantData);

        $this->tenantsRepository
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('add');

        $this->translator
            ->method('trans')
            ->willReturn('Tenant creado exitosamente');

        $result = $this->useCase->handler();

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
        $this->assertSame('Tenant creado exitosamente', $result->getMessage());
    }

    public function testHandlerWithDuplicateNitReturnsError(): void
    {
        $tenantData = [
            'name'        => 'Empresa Duplicada',
            'description' => 'Descripción',
            'nit'         => '900123456',
            'phone'       => '3001234567',
            'prefix'      => '+57',
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);

        $this->request->method('get')
            ->with('tenants', [])
            ->willReturn($tenantData);

        $existingTenant = $this->createMock(Tenants::class);
        $this->tenantsRepository
            ->method('findOneBy')
            ->willReturn($existingTenant);

        $this->log->method('handler')->willReturn(null);

        $this->translator->method('trans')
            ->willReturn('El tenant ya existe');

        $result = $this->useCase->handler();

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
        $this->assertNotNull($result->getMessage());
    }

    public function testHandlerWithSubmittedInvalidFormDoesNotPersist(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(false);

        $this->entityManager
            ->expects($this->never())
            ->method('add');

        $result = $this->useCase->handler();

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerCatchesUnexpectedException(): void
    {
        $tenantData = [
            'name'        => 'Empresa Test',
            'description' => 'Descripción',
            'nit'         => '900123456',
            'phone'       => '3001234567',
            'prefix'      => '+57',
        ];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);

        $this->request->method('get')
            ->with('tenants', [])
            ->willReturn($tenantData);

        $this->tenantsRepository
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->method('add')
            ->willThrowException(new \RuntimeException('Error de base de datos'));

        $this->log->method('handler')->willReturn(null);

        $this->translator->method('trans')->willReturn('Error de registro');

        $result = $this->useCase->handler();

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }
}
