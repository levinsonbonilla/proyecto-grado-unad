<?php

namespace App\Form\Dashboard;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;

class TenantsType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                "attr" => [
                    "require" => true
                ]
            ])
            ->add('description', null, [
                "attr" => [
                    "require" => true
                ]
            ])
            ->add('nit', NumberType::class, [
                "attr" => [
                    "require" => true
                ]
            ])
            ->add('prefix', HiddenType::class , [
                "attr" => [
                    "require" => true
                ]
            ])
            ->add('phone', TelType::class, [
                "attr" => [
                    "require" => true
                ]
            ]);
    }
}
