<?php

namespace App\Tests\Unit\Handler\UseCase\Modules\Products\Colors;

use App\Entity\Products\Colors\Colors;
use App\Handler\UseCase\Modules\Products\Colors\EditColorsUseCase;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\ReturnHandler\FormReturn;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class EditColorsUseCaseTest extends TestCase
{
    private FormFactoryInterface&MockObject $formFactory;
    private FormInterface&MockObject $form;
    private FormInterface&MockObject $childForm;
    private RequestStack&MockObject $requestStack;
    private Request&MockObject $request;
    private LogInterface&MockObject $log;
    private TranslatorInterface&MockObject $translator;
    private CustomeEntityManagerInterface&MockObject $entityManager;
    private Colors&MockObject $entity;
    private EditColorsUseCase $useCase;

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

        $this->log           = $this->createMock(LogInterface::class);
        $this->translator    = $this->createMock(TranslatorInterface::class);
        $this->entityManager = $this->createMock(CustomeEntityManagerInterface::class);

        $this->entity = $this->createMock(Colors::class);

        $this->useCase = new EditColorsUseCase(
            $this->requestStack,
            $this->formFactory,
            $this->log,
            $this->translator,
            $this->entityManager,
        );
    }

    private function stubEntityGetters(): void
    {
        $this->entity->method('getName')->willReturn('Rojo');
        $this->entity->method('getHexCode')->willReturn('#ff0000');
        $this->entity->method('isActive')->willReturn(true);
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

        $this->childForm->expects($this->atLeastOnce())->method('setData');

        $result = $this->useCase->handler($this->entity);

        $this->assertFalse($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerWithValidSubmissionEditsColor(): void
    {
        $editData = ['name' => 'Rojo intenso', 'hexCode' => '#cc0000', 'activated' => true];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('colors', [])->willReturn($editData);

        $this->stubEntityGetters();
        $this->entity->method('edit')->willReturnSelf();
        $this->entity->expects($this->once())->method('activate');
        $this->entity->expects($this->never())->method('deactivate');

        $this->entityManager->expects($this->once())->method('add');
        $this->translator->method('trans')->willReturn('Color editado');

        $result = $this->useCase->handler($this->entity);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
        $this->assertSame('Color editado', $result->getMessage());
    }

    public function testHandlerWithValidSubmissionDeactivatesColor(): void
    {
        $editData = ['name' => 'Rojo', 'hexCode' => '#ff0000', 'activated' => false];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('colors', [])->willReturn($editData);

        $this->stubEntityGetters();
        $this->entity->method('edit')->willReturnSelf();
        $this->entity->expects($this->once())->method('deactivate');
        $this->entity->expects($this->never())->method('activate');

        $this->translator->method('trans')->willReturn('Color editado');

        $this->useCase->handler($this->entity);
    }

    public function testHandlerWithSubmittedInvalidFormDoesNotPersist(): void
    {
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(false);

        $this->entityManager->expects($this->never())->method('add');

        $result = $this->useCase->handler($this->entity);

        $this->assertTrue($result->isProcess());
        $this->assertFalse($result->isError());
    }

    public function testHandlerCatchesExceptionAndReturnsError(): void
    {
        $editData = ['name' => 'Rojo', 'hexCode' => '#ff0000', 'activated' => true];

        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('isValid')->willReturn(true);
        $this->request->method('get')->with('colors', [])->willReturn($editData);

        $this->entity->method('edit')->willThrowException(new \RuntimeException('Error inesperado'));

        $this->log->method('handler')->willReturn(null);
        $this->translator->method('trans')->willReturn('Error al editar');

        $result = $this->useCase->handler($this->entity);

        $this->assertTrue($result->isProcess());
        $this->assertTrue($result->isError());
    }
}
