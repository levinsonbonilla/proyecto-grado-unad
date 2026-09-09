<?php

namespace App\Form\Dashboard;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProfileType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'required' => true,
                'attr' => [
                    "readonly" => true
                ],
                'constraints' => [
                    new Email([
                        'message' => $this->translator->trans("invalid_email_message", [], 'login'),
                        'mode' => 'html5',
                    ]),
                ],
            ])
            ->add('name', null, [
                'required' => true
            ])
            ->add('lastName', null, [
                'required' => true
            ])
            ->add('password', PasswordType::class, [
                'required' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Length([
                        'min' => 0,
                        'minMessage' => $this->translator->trans("password_weak_message", [], 'login'),
                        'max' => 4096,
                    ]),
                ],
            ])
            ->add('confirm_password', PasswordType::class, [
                'required' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => 'form-control',
                ]
            ])
            ->add('dateOfBirth', DateType::class, [
                'required' => false
            ])
            ->add('prefix', HiddenType::class, [
                "required" => false
            ])
            ->add('phone', TelType::class, [
                "required" => false
            ])
            ->add('address', null, [
                'required' => false
            ])
            ->add('image', FileType::class, [
                "required" => false
            ])
        ;
    }
}
