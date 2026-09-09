<?php

namespace App\Handler\UseCase\Dashboard\Tenants;

use App\ArgumentHandler\TenantsArgument;
use App\Entity\Tenants\Tenants;
use App\Exception\GenericException;
use App\Form\Dashboard\TenantsType;
use App\Handler\Shared\AbstractAddHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Dashboard\Tenants\AddTenantInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\TenantsRepository;
use App\ReturnHandler\FormReturn;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AddTenantUseCase extends AbstractAddHandler implements AddTenantInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly TenantsRepository $tenantsRepository,
        private readonly CustomeEntityManagerInterface $customeEntityManager
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(): FormReturn
    {
        return $this->process(TenantsType::class);
    }

    protected function successMessage(): string
    {
        return $this->translator->trans("tenant_created_successfully", [], 'tenants');
    }

    protected function add(array $data, array $additionalData): void
    {
        $argument = new TenantsArgument($data);

        $tenant = $this->tenantsRepository->findOneBy(["nit" => $argument->getNit()]);
        if (!empty($tenant)) {
            throw new GenericException($this->translator->trans("existing_tenant", [], "tenants"), 400);
        }

        $tenant = new Tenants();
        $tenant->add($argument);
        $this->customeEntityManager->add($tenant, true);
    }
}
