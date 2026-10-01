<?php

namespace App\Form\Dashboard;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;

final class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('address', TextareaType::class, [
                'required' => false,
                'constraints' => [new Length(max: 500)],
            ])
            ->add('phone', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 50)],
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'constraints' => [new Email(), new Length(max: 255)],
            ]);
    }
}
