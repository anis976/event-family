<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;

final class DeleteAccountFormType extends AbstractType
{
    public const string CONFIRM_PHRASE = 'SUPPRIMER';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'current-password',
                    'class' => 'form-control ef-input',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le mot de passe actuel est obligatoire.'),
                    new UserPassword(message: 'Le mot de passe actuel est incorrect.'),
                ],
            ])
            ->add('confirmPhrase', TextType::class, [
                'label' => 'Confirmation',
                'mapped' => false,
                'help' => 'Saisis le mot SUPPRIMER en majuscules pour confirmer.',
                'attr' => [
                    'class' => 'form-control ef-input',
                    'placeholder' => self::CONFIRM_PHRASE,
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new NotBlank(message: 'La phrase de confirmation est obligatoire.'),
                    new EqualTo(
                        value: self::CONFIRM_PHRASE,
                        message: 'Tu dois saisir exactement le mot SUPPRIMER.',
                    ),
                ],
            ])
            ->add('agreeDeletion', CheckboxType::class, [
                'label' => 'Je comprends que cette action est définitive et irréversible.',
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'Tu dois confirmer que tu comprends les conséquences.'),
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
