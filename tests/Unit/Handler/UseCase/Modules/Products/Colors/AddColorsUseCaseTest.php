<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Products\Colors;

use App\Entity\Tenants\Domains\Domains;
use App\Form\Modules\Products\ColorsType;
use App\Handler\UseCase\Modules\Products\Colors\AddColorsUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\ReturnHandler\FormReturn;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class AddColorsUseCaseTest extends TestCase
{
    private FormFactoryInterface&MockObject $formFactory;
    private FormInterface&MockObject $form;
    private RequestStack&MockObject $requestStack;
    private Request&MockObject $request;
    private LogInterface&MockObject $log;
    private TranslatorInterface&MockObject $translator;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private GetDomainDataInterface&MockObject $getDomainData;
    private AddColorsUseCase $useCase;

    protected function setUp(): void
    {
        $this->form = $this->createMock(FormInterface::class);
        $this->form->method('handleRequest')->willReturnSelf();
        $this->form->method('getName')->willReturn('colors');

        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->formFactory->method('create')->willReturn($this->form);

        $this->request        = $this->createMock(Request::class);
        $this->request->files = new FileBag([]);
        $this->requestStack   = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($this->request);

        $this->log            = $this->createMock(LogInterface::class);
        $this->translator     = $this->createMock(TranslatorInterface::class);
        $this->entityManager  = $this->createMock(CustomeEntityManagerInterface::class);
        $this->getDomainData  = $this->createMock(GetDomainDataInterface::class);
        $this->getDomainData->method('getDomain')->willReturn($this->createMock(Domains::class));

        $this->useCase = new AddColorsUseCase(
            $this->requestStack,
            $this->formFactory,
            $this->log,
            $this->translator,
            $this->getDomainData,
            $this->entityManager,
        );
    }

    public function testHandlerReturnsFormReturnInstance(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);

        $result = $this->useCase->handler(ColorsType::class);

        $this->assertInstanceOf(FormReturn::class, $result);
    }

    public function testHandlerWithNoSubmissionDoesNotProcess(): void
    {
        $this->form->method('isSubmitted')->willReturn(false);

        $result = $this->useCase->handler(ColorsType::class);

        $this->assertFalse($result->isProcess());
        $this->assertFalse($result->isError());
        $this->assertNull($result->getMessage());
    }

    public function testHandlerWithValidFormCreatesColor(): void
    {
        $data = ['name' => 'Rojo', 'hexCode' => '#ff0000'];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('colors', [])->willReturn($data);

        $this->entityManager->expects($this->once())->method('add');
        $this->translator->method('trans')->willReturn('Color creado');

        $result = $this->useCase->handler(ColorsType::class);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerWithSubmittedInvalidFormDoesNotPersist(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(false);

        $this->entityManager->expects($this->never())->method('add');

        $result = $this->useCase->handler(ColorsType::class);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerCatchesUnexpectedException(): void
    {
        $data = ['name' => 'Azul', 'hexCode' => '#0000ff'];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('colors', [])->willReturn($data);

        $this->entityManager->method('add')
            ->willThrowException(new \RuntimeException('Error de base de datos'));

        $this->log->method('handler')->willReturn(null);
        $this->translator->method('trans')->willReturn('Error de registro');

        $result = $this->useCase->handler(ColorsType::class);

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }
}
