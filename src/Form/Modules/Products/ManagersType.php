<?php

namespace App\Form\Modules\Products;

use App\Entity\Products\Categories\Categories;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use App\Form\Modules\Products\ProductVariantType;
use Symfony\Component\Form\CallbackTransformer;

class ManagersType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'required' => true,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('description', CKEditorType::class, [
                'config' => [
                    'toolbar' => [['Bold', 'Italic', 'Underline'], ['NumberedList', 'BulletedList'], ['Link', 'Unlink'], ['RemoveFormat']],
                    'height' => 300,
                    'removePlugins' => ''
                ],
                'attr' => [
                    'class' => 'form-control',
                ]
            ])

            ->add('basePrice', TextType::class, [
                'required' => true,
                'attr' => [
                    'class' => 'form-control col-md-7 col-xs-12',
                    'placeholder' => '0',
                ],
            ])
            ->add('publicPrice', TextType::class, [
                'required' => true,
                'attr' => [
                    'class' => 'form-control col-md-7 col-xs-12',
                    'placeholder' => '0',
                ],
            ])

            ->add('stock', IntegerType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'placeholder' => '0',
                ],
            ])
            ->add('productsCategories', EntityType::class, [
                'class' =>  Categories::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
            ])

            ->add('variants', CollectionType::class, [
                'entry_type' => ProductVariantType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'label' => false,
                'attr' => ['class' => 'variant-collection'],
            ])
        ;

        $cleanTransformer = new CallbackTransformer(
            fn($value) => $value,
            function ($value) {
                if ($value === null) {
                    return null;
                }

                $value = trim($value);

                $value = str_replace(['.', ','], '', $value);

                return (int) $value;
            }
        );

        $builder->get('basePrice')->addModelTransformer($cleanTransformer);
        $builder->get('publicPrice')->addModelTransformer($cleanTransformer);
    }
}
