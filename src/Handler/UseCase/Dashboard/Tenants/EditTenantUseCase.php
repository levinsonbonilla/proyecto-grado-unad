<?php

namespace App\Handler\UseCase\Dashboard\Tenants;

use App\ArgumentHandler\TenantsArgument;
use App\Entity\Tenants\Tenants;
use App\Form\Dashboard\TenantsType;
use App\Handler\Shared\AbstractEditHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Dashboard\Tenants\EditTenantInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\TenantsRepository;
use App\ReturnHandler\FormReturn;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EditTenantUseCase extends AbstractEditHandler implements EditTenantInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly TenantsRepository $tenantsRepository
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(Tenants $tenant): FormReturn
    {
        return $this->process($tenant);
    }

    protected function formType(): string
    {
        return TenantsType::class;
    }

    protected function formName(): string
    {
        return 'tenants';
    }

    protected function successMessage(): string
    {
        return $this->translator->trans("tenant_edited_success", [], 'tenants');
    }

    protected function complementForm(FormInterface $form): FormInterface
    {
        $yes = $this->translator->trans("yes", [], "users");
        $no = $this->translator->trans("no", [], "users");

        return $form
            ->add('nit', null, ['attr' => ['readonly' => true]])
            ->add('activated', ChoiceType::class, [
                'choices' => [$yes => true, $no => false],
                'required' => true,
            ])
            ->add('isPrincipal', ChoiceType::class, [
                'choices' => [$yes => true, $no => false],
                'required' => true,
            ]);
    }

    protected function assembleForm(FormInterface $form, object $entity): FormInterface
    {
        $form->get('name')->setData($entity->getName());
        $form->get('description')->setData($entity->getDescription());
        $form->get('nit')->setData($entity->getNit());
        $form->get('prefix')->setData($entity->getPrefix());
        $form->get('phone')->setData($entity->getPhone());
        $form->get('activated')->setData($entity->isActive());
        $form->get('isPrincipal')->setData($entity->isPrincipal());

        return $form;
    }

    protected function edit(array $data, object $entity): void
    {
        $argument = new TenantsArgument($data);
        $entity->edit($argument);
        $data["activated"] ? $entity->activate() : $entity->deactivate();

        ($data["isPrincipal"] ?? false) ? $entity->markAsPrincipal() : $entity->unmarkAsPrincipal();
        $this->customeEntityManager->add($entity, true);
        $this->tenantsRepository->invalidateCacheTenantId($entity->getId());
    }
}
