<?php

namespace App\Form\Security;

use App\Entity\Users\Users;
use App\Interface\Configuration\GetDomainDataInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegisterType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly GetDomainDataInterface $getDomainData,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        if ($this->getDomainData->getTenantCache()->isPrincipal()) {
            $builder
                ->add('businessName', null, [
                    'required' => true,
                    'constraints' => [
                        new NotBlank(['message' => $this->translator->trans('business_name_required_message', [], 'login')]),
                    ],
                ])
                ->add('phone', null, [
                    'required' => true,
                    'constraints' => [
                        new NotBlank(['message' => $this->translator->trans('phone_required_message', [], 'login')]),
                    ],
                ]);
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
            ->add('password', PasswordType::class, [
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans("password_weak_message", [], 'login'),
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => $this->translator->trans("password_weak_message", [], 'login'),
                        'max' => 4096,
                    ]),
                ],
            ])
            ->add('confirm_password', PasswordType::class, [
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans("confirmation_does_not_match_message", [], 'login'),
                    ]),
                ],
            ])
            ->add('name', null, ['required' => true])
            ->add('lastName', null, ['required' => true]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
    }
}
