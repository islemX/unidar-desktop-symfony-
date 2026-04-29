<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\UserRole;
use App\Validator\Constraints\ValidEmailDomain;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'required'    => true,
                'attr'        => [
                    'autocomplete' => 'email',
                    'data-email-validate' => 'true',  // hook for JS real-time check
                ],
                'constraints' => [
                    new NotBlank(message: 'Please enter your email address.'),
                    new Email(mode: 'html5', message: 'Please enter a valid email address.'),
                    new ValidEmailDomain(),
                ],
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'Password'],
                'second_options' => ['label' => 'Confirm Password'],
            ])
            ->add('fullName', TextType::class)
            ->add('role', EnumType::class, [
                'class'        => UserRole::class,
                'choices'      => [UserRole::Student, UserRole::Owner],
                'choice_label' => fn(UserRole $r) => match($r) {
                    UserRole::Student => 'Student',
                    UserRole::Owner   => 'Property Owner',
                    default           => $r->getLabel(),
                },
                'placeholder'  => 'Select...',
            ])
            ->add('university', TextType::class, [
                'required' => false,
            ])
            ->add('phone', TextType::class, [
                'required' => false,
            ])
            ->add('gender', ChoiceType::class, [
                'choices' => [
                    'Male' => 'male',
                    'Female' => 'female',
                ],
                'placeholder' => 'Select Gender...',
                'required' => false,
            ])
            ->add('preferredLat', HiddenType::class, [
                'required' => false,
            ])
            ->add('preferredLng', HiddenType::class, [
                'required' => false,
            ])
            ->add('preferredAddress', TextType::class, [
                'required' => false,
            ])
            ->add('universityAddress', TextType::class, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
