<?php

namespace App\Handler\UseCase\Modules\Products\Colors;

use App\ArgumentHandler\ColorsArgument;
use App\Entity\Products\Colors\Colors;
use App\Form\Modules\Products\ColorsType;
use App\Handler\Shared\AbstractAddHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Modules\Products\Colors\AddColorsInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\ReturnHandler\FormReturn;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AddColorsUseCase extends AbstractAddHandler implements AddColorsInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(): FormReturn
    {
        return $this->process(ColorsType::class);
    }

    protected function successMessage(): string
    {
        return $this->translator->trans("color_created_successfully", [], 'modules');
    }

    protected function add(array $data, array $additionalData): void
    {
        $domain = $this->getDomainData->getDomain();
        $color = new Colors();
        $argument = new ColorsArgument($data, $domain);
        $color->add($argument);
        $this->customeEntityManager->add($color, true);
    }
}
