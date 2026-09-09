<?php



namespace App\Form\CustomeType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\EventListener\FixUrlProtocolListener;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomeUrlType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (null !== $options['default_protocol']) {
            $builder->addEventSubscriber(new FixUrlProtocolListener($options['default_protocol']));
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if ($options['default_protocol']) {
            $view->vars['attr']['inputmode'] = 'url';
            $view->vars['type'] = 'url';
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'default_protocol' => static function (Options $options) {
                trigger_deprecation('symfony/form', '7.1', 'Not configuring the "default_protocol" option when using the UrlType is deprecated. It will default to "null" in 8.0.');

                return 'http';
            },
            'invalid_message' => 'Please enter a valid URL.',
        ]);

        $resolver->setAllowedTypes('default_protocol', ['null', 'string']);
    }

    public function getParent(): ?string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'url';
    }
}
