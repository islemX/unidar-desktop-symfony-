<?php

namespace App\Form;

use App\Entity\Report;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reportType', ChoiceType::class, [
                'choices' => [
                    'User' => 'user',
                    'Listing' => 'listing',
                ],
            ])
            ->add('reason', ChoiceType::class, [
                'choices' => [
                    'Spam' => 'spam',
                    'Inappropriate' => 'inappropriate',
                    'Fraud' => 'fraud',
                    'Other' => 'other',
                ],
            ])
            ->add('description', TextareaType::class)
            ->add('priority', ChoiceType::class, [
                'choices' => [
                    'Low' => 'low',
                    'Medium' => 'medium',
                    'High' => 'high',
                ],
            ])
            ->add('reportedUserId', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('reportedListingId', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Report::class,
        ]);
    }
}
