<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<User>
 */
final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'nom@exemple.com',
                    'autocomplete' => 'email',
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Prénom',
                    'autocomplete' => 'given-name',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Nom',
                    'autocomplete' => 'family-name',
                ],
            ])
            ->add('pseudo', TextType::class, [
                'required' => false,
                'label' => false,
                'attr' => [
                    'placeholder' => 'Pseudo (optionnel)',
                    'autocomplete' => 'username',
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => '••••••••',
                        'autocomplete' => 'new-password',
                    ],
                    'constraints' => [
                        new NotBlank(message: 'Please enter a password'),
                        new Length(min: 8, minMessage: 'Your password should be at least {{ limit }} characters'),
                    ],
                ],
                'second_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => '••••••••',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'invalid_message' => 'The password fields must match.',
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => false,
                'constraints' => [
                    new IsTrue(message: 'You must accept the terms of use.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
