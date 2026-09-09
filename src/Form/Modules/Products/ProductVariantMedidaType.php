<?php

namespace App\Form\Modules\Products;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ProductVariantMedidaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rowId', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('medidaName', TextType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('stock', IntegerType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['min' => 0],
            ]);
    }
}
