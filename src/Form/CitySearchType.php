<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class CitySearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('city', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Enter city name...',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a city name',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-Z\s\-]+$/',
                        'message' => 'City name can only contain letters, spaces, and hyphens',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'POST',
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'city_search',
        ]);
    }
}