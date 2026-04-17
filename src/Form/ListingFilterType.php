<?php

namespace App\Form;

use App\Enum\PropertyType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ListingFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('minPrice', NumberType::class, [
                'required' => false,
            ])
            ->add('maxPrice', NumberType::class, [
                'required' => false,
            ])
            ->add('bedrooms', IntegerType::class, [
                'required' => false,
            ])
            ->add('propertyType', EnumType::class, [
                'class' => PropertyType::class,
                'required' => false,
                'placeholder' => 'All',
            ])
            ->add('genderPreference', ChoiceType::class, [
                'choices' => [
                    'Male' => 'male',
                    'Female' => 'female',
                ],
                'required' => false,
                'placeholder' => 'All',
            ])
            ->add('lat', HiddenType::class, [
                'required' => false,
            ])
            ->add('lng', HiddenType::class, [
                'required' => false,
            ])
            ->add('maxDistance', NumberType::class, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}
