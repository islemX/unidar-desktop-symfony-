<?php

namespace App\Form;

use App\Entity\RoommatePreference;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoommatePreferenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('budgetMin', NumberType::class, [
                'required' => false,
            ])
            ->add('budgetMax', NumberType::class, [
                'required' => false,
            ])
            ->add('cleanlinessLevel', ChoiceType::class, [
                'choices' => [
                    '1' => 1,
                    '2' => 2,
                    '3' => 3,
                    '4' => 4,
                    '5' => 5,
                ],
                'required' => false,
            ])
            ->add('smokingPreference', ChoiceType::class, [
                'choices' => [
                    'No preference' => null,
                    'Non-smoker' => 'non_smoker',
                    'Smoker' => 'smoker',
                ],
                'required' => false,
            ])
            ->add('noiseTolerance', ChoiceType::class, [
                'choices' => [
                    'No preference' => null,
                    'Quiet' => 'quiet',
                    'Moderate' => 'moderate',
                    'Loud' => 'loud',
                ],
                'required' => false,
            ])
            ->add('sleepSchedule', ChoiceType::class, [
                'choices' => [
                    'No preference' => null,
                    'Early Bird' => 'early_bird',
                    'Night Owl' => 'night_owl',
                    'Flexible' => 'flexible',
                ],
                'required' => false,
            ])
            ->add('genderPreference', ChoiceType::class, [
                'choices' => [
                    'No preference' => null,
                    'Male' => 'male',
                    'Female' => 'female',
                    'Any' => 'any',
                ],
                'required' => false,
            ])
            ->add('ageMin', IntegerType::class, [
                'required' => false,
            ])
            ->add('ageMax', IntegerType::class, [
                'required' => false,
            ])
            ->add('guests', ChoiceType::class, [
                'choices' => [
                    'No preference' => null,
                    'Never' => 'never',
                    'Rarely' => 'rarely',
                    'Sometimes' => 'sometimes',
                    'Often' => 'often',
                ],
                'required' => false,
            ])
            ->add('pets', ChoiceType::class, [
                'choices' => [
                    'No preference' => null,
                    'No Pets' => 'no_pets',
                    'Cats' => 'cats',
                    'Dogs' => 'dogs',
                    'Any' => 'any',
                ],
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RoommatePreference::class,
        ]);
    }
}
