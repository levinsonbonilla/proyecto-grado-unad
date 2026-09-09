<?php

namespace App\Form\Modules\Products;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ProductVariantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rowId', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])

            ->add('order', IntegerType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['min' => 1],
            ])
            ->add('colorName', TextType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('colorHex', ColorType::class, [
                'mapped' => false,
                'required' => false,
            ])

            ->add('medidas', CollectionType::class, [
                'entry_type' => ProductVariantMedidaType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,

                'prototype_name' => '__medida_name__',
                'label' => false,
                'attr' => ['class' => 'variant-medida-collection'],
            ])

            ->add('stock', IntegerType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['min' => 0],
            ])
            ->add('images', CollectionType::class, [
                'entry_type' => ProductVariantImageType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,

                'prototype_name' => '__image_name__',
                'label' => false,
                'attr' => ['class' => 'variant-image-collection'],
            ]);
    }
}
