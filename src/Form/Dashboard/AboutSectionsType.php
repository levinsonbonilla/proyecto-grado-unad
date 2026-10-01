<?php

namespace App\Form\Dashboard;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class AboutSectionsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'required' => true,
                'constraints' => [new NotBlank(), new Length(max: 255)],
            ])
            ->add('text', TextareaType::class, [
                'required' => true,
                'constraints' => [new NotBlank()],
            ])
            ->add('image', FileType::class, [
                'required' => false,
            ])
            ->add('position', NumberType::class, [
                'required' => false,
            ]);
    }
}
