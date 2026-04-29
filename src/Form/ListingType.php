<?php

namespace App\Form;

use App\Entity\Listing;
use App\Enum\PropertyType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;

class ListingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class)
            ->add('description', TextareaType::class)
            ->add('address', TextType::class)
            ->add('latitude', HiddenType::class)
            ->add('longitude', HiddenType::class)
            ->add('price', NumberType::class, [
                'scale' => 2,
            ])
            ->add('bedrooms', IntegerType::class)
            ->add('bedsCount', IntegerType::class)
            ->add('capacity', IntegerType::class)
            ->add('bathrooms', IntegerType::class)
            ->add('propertyType', EnumType::class, [
                'class' => PropertyType::class,
            ])
            ->add('genderPreference', ChoiceType::class, [
                'choices' => [
                    'Any' => null,
                    'Male' => 'male',
                    'Female' => 'female',
                ],
                'required' => false,
            ])
            ->add('availableFrom', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('availableUntil', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('images', FileType::class, [
                'multiple' => true,
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new All([
                        new File([
                            'maxSize' => '5M',
                            'mimeTypes' => [
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ],
                        ]),
                    ]),
                ],
            ])
            // Base64-encoded PNG signature captured by the canvas pad (UnidarSignaturePad).
            // Unmapped — handled in ListingController which decodes & stores the file
            // and sets Listing::ownerSignaturePath.
            ->add('ownerSignature', HiddenType::class, [
                'mapped'   => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Listing::class,
        ]);
    }
}
