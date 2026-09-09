<?php

namespace App\Handler\UseCase\Modules\Products\Medidas;

use App\ArgumentHandler\MedidasArgument;
use App\Entity\Products\Medidas\Medidas;
use App\Form\Modules\Products\MedidasType;
use App\Handler\Shared\AbstractAddHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Modules\Products\Medidas\AddMedidasInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\ReturnHandler\FormReturn;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AddMedidasUseCase extends AbstractAddHandler implements AddMedidasInterface
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
        return $this->process(MedidasType::class);
    }

    protected function successMessage(): string
    {
        return $this->translator->trans("medida_created_successfully", [], 'modules');
    }

    protected function add(array $data, array $additionalData): void
    {
        $domain = $this->getDomainData->getDomain();
        $medida = new Medidas();
        $argument = new MedidasArgument($data, $domain);
        $medida->add($argument);
        $this->customeEntityManager->add($medida, true);
    }
}
