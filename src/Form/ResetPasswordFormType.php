<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ResetPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'invalid_message' => 'Les mots de passe ne correspondent pas.',
            'first_options' => [
                'label' => 'Nouveau mot de passe',
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => 'form-control form-control-lg',
                    'placeholder' => '••••••••',
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
                'label' => 'Confirmer le mot de passe',
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => 'form-control form-control-lg',
                    'placeholder' => '••••••••',
                ],
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'novalidate' => 'novalidate',
                'data-turbo' => 'false',
            ],
        ]);
    }
}
