<?php

namespace App\Form\Messages;

use App\Entity\Users\Users;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ComposeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isSuperAdmin   = $options['is_super_admin'];
        $availableUsers = $options['available_users'];

        if ($isSuperAdmin) {
            $builder->add('toUsers', EntityType::class, [
                'class'        => Users::class,
                'choices'      => $availableUsers,
                'multiple'     => true,
                'expanded'     => false,
                'choice_label' => fn(Users $u) => $u->getName() . ' ' . $u->getLastName() . ' (' . $u->getEmail() . ')',
                'choice_value' => fn(?Users $u) => $u?->getId(),
                'attr'         => ['class' => 'select2', 'multiple' => 'multiple'],
            ]);
        } elseif (count($availableUsers) > 1) {
            $builder->add('toUser', EntityType::class, [
                'class'        => Users::class,
                'choices'      => $availableUsers,
                'choice_label' => fn(Users $u) => $u->getName() . ' ' . $u->getLastName(),
                'choice_value' => fn(?Users $u) => $u?->getId(),
            ]);
        }

        $builder

            ->add('subject', TextType::class, [
                'required' => false,
                'attr'     => ['maxlength' => 255],
            ])
            ->add('message', TextareaType::class, ['attr' => ['rows' => 6]])
            ->add('images', FileType::class, [
                'required' => false,
                'multiple' => true,
                'attr'     => ['accept' => 'image/*', 'multiple' => 'multiple'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'is_super_admin'  => false,
            'available_users' => [],
        ]);
    }
}
