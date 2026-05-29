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
                'constraints' => [
                    new NotBlank(message: 'L\'adresse e-mail est obligatoire.'),
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Prénom',
                    'autocomplete' => 'given-name',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le prénom est obligatoire.'),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Nom',
                    'autocomplete' => 'family-name',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
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
                        new NotBlank(message: 'Le mot de passe est obligatoire.'),
                        new Length(
                            min: 8,
                            minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        ),
                    ],
                ],
                'second_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => '••••••••',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => false,
                'constraints' => [
                    new IsTrue(message: 'Tu dois accepter les conditions d\'utilisation.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'validation_groups' => ['Default', 'Registration'],
        ]);
    }
}
