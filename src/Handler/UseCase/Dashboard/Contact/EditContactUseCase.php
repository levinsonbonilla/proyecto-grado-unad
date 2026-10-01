<?php

namespace App\Handler\UseCase\Dashboard\Contact;

use App\Entity\Tenants\Domains\Domains;
use App\Form\Dashboard\ContactType;
use App\Handler\Shared\AbstractEditHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Dashboard\Contact\EditContactInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\ReturnHandler\FormReturn;
use App\Util\StringUtil;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EditContactUseCase extends AbstractEditHandler implements EditContactInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly DomainsRepository $domainsRepository,
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(Domains $domain): FormReturn
    {
        return $this->process($domain);
    }

    protected function formType(): string
    {
        return ContactType::class;
    }

    protected function formName(): string
    {
        return 'contact';
    }

    protected function successMessage(): string
    {
        return $this->translator->trans('contact_saved_successfully', [], 'contact_settings');
    }

    protected function assembleForm(FormInterface $form, object $entity): FormInterface
    {
        $form->get('address')->setData($entity->getContactAddress());
        $form->get('phone')->setData($entity->getContactPhone());
        $form->get('email')->setData($entity->getContactEmail());

        return $form;
    }

    protected function edit(array $data, object $entity): void
    {
        $entity->changeContact(
            $data['address'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null
        );

        $this->customeEntityManager->add($entity, true);
        $this->domainsRepository->invalidateCacheDomain(StringUtil::removeHTTP($entity->getDomain()));
    }
}
