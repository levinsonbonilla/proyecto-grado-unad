<?php

namespace App\Form\Dashboard;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Tenants;
use App\Repository\Tenants\Domains\DomainsRepository;
use App\Repository\Tenants\TenantsRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class UsersType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly DomainsRepository $domainsRepository,
        private readonly TenantsRepository $tenantsRepository
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [
            $this->translator->trans("ROLE_ADMIN", [], "users") => 'ROLE_ADMIN',
            $this->translator->trans("ROLE_USER", [], "users") => 'ROLE_USER',
        ];

        if ($this->security->isGranted("ROLE_SUPER_ADMIN")) {
            $choices = array_merge(
                [$this->translator->trans("ROLE_SUPER_ADMIN", [], "users") => 'ROLE_SUPER_ADMIN'],
                $choices
            );
        }

        $builder
            ->add('email', EmailType::class, [
                'required' => true,
                'constraints' => [
                    new Email([
                        'message' => $this->translator->trans("invalid_email_message", [], 'login'),
                        'mode' => 'html5',
                    ]),
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'choices' => $choices,
                'multiple' => false,
                'expanded' => false,
            ])
            ->add('name', null, [
                'attr' => [
                    'requiere' => true
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans("required_data", [], 'messages'),
                    ]),
                ],
            ])
            ->add('lastName', null, [
                'attr' => [
                    'requiere' => true
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans("required_data", [], 'messages'),
                    ]),
                ],
            ]);

        $tenants = [];
        if ($this->security->isGranted("ROLE_SUPER_ADMIN")) {
            $tenants = $this->tenantsRepository->findBy(["active" => true]);
            $builder
                ->add('tenant', EntityType::class, [
                    'class' => Tenants::class,
                    'choices' => $tenants,
                    'choice_label' => 'name',
                ]);
        }

        if ($this->security->isGranted("ROLE_SUPER_ADMIN") || $this->security->isGranted("ROLE_ADMIN")) {
            $tenant = count($tenants) > 0 ?
                reset($tenants) :
                $this->security->getUser()->getDomain()->getTenant();

            $domains = $this->domainsRepository->findBy([
                "tenant" => $tenant,
                "active" => true
            ]);

            $builder
                ->add('domain', EntityType::class, [
                    'class' => Domains::class,
                    'choices' => $domains,
                    'choice_label' => 'domain',
                ]);
        }

        if ($this->security->isGranted("ROLE_SUPER_ADMIN")) {
            $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
                $tenantId = $event->getData()['tenant'] ?? null;
                if (empty($tenantId)) {
                    return;
                }

                $selectedTenant = $this->tenantsRepository->find($tenantId);
                if ($selectedTenant === null) {
                    return;
                }

                $event->getForm()->add('domain', EntityType::class, [
                    'class' => Domains::class,
                    'choices' => $this->domainsRepository->findBy([
                        'tenant' => $selectedTenant,
                        'active' => true,
                    ]),
                    'choice_label' => 'domain',
                ]);
            });
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
    }
}
