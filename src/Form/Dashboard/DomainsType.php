<?php

namespace App\Form\Dashboard;

use App\Form\CustomeType\CustomeUrlType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DomainsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('domain', CustomeUrlType::class, [

                "default_protocol" => 'http',
                "attr" => [
                    "require" => true,
                ]
            ])

            ->add('name', TextType::class, [
                "required" => false,
                "attr" => [
                    "maxlength" => 100,
                ]
            ])
            ->add('image', FileType::class, [
                "attr" => [
                    "require" => false
                ]
            ])
            ->add('notificationEmail', EmailType::class, [
                "attr" => [
                    "require" => true
                ]
            ])
            ->add('supportEmail', EmailType::class, [
                "attr" => [
                    "require" => true
                ]
            ])

            ->add('facebookUrl', UrlType::class, [
                "required" => false,
                "default_protocol" => 'https',
            ])
            ->add('instagramUrl', UrlType::class, [
                "required" => false,
                "default_protocol" => 'https',
            ])
            ->add('pinterestUrl', UrlType::class, [
                "required" => false,
                "default_protocol" => 'https',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['allow_extra_fields' => true]);
    }
}
