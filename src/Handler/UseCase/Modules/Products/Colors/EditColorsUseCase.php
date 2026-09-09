<?php

namespace App\Handler\UseCase\Modules\Products\Colors;

use App\ArgumentHandler\ColorsArgument;
use App\Entity\Products\Colors\Colors;
use App\Form\Modules\Products\ColorsType;
use App\Handler\Shared\AbstractEditHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Modules\Products\Colors\EditColorsInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\ReturnHandler\FormReturn;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EditColorsUseCase extends AbstractEditHandler implements EditColorsInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(Colors $color): FormReturn
    {
        return $this->process($color);
    }

    protected function formType(): string
    {
        return ColorsType::class;
    }

    protected function formName(): string
    {
        return 'colors';
    }

    protected function successMessage(): string
    {
        return $this->translator->trans("color_edited_success", [], 'modules');
    }

    protected function complementForm(FormInterface $form): FormInterface
    {
        $yes = $this->translator->trans("yes", [], "users");
        $no  = $this->translator->trans("no", [], "users");

        return $form->add('activated', ChoiceType::class, [
            'choices'  => [$yes => true, $no => false],
            'required' => true,
        ]);
    }

    protected function assembleForm(FormInterface $form, object $entity): FormInterface
    {
        $form->get('name')->setData($entity->getName());
        $form->get('hexCode')->setData($entity->getHexCode());
        $form->get('activated')->setData($entity->isActive());
        return $form;
    }

    protected function edit(array $data, object $entity): void
    {

        $argument = new ColorsArgument($data);
        $entity->edit($argument);
        !empty($data['activated']) ? $entity->activate() : $entity->deactivate();
        $this->customeEntityManager->add($entity, true);
    }
}
