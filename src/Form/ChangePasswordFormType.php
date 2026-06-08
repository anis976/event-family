<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ChangePasswordFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $t = fn (string $id): string => $this->translator->trans($id);

        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => $t('ui.profile.form.label.current_password'),
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'current-password',
                    'autofocus' => true,
                    'class' => 'form-control ef-input',
                    'placeholder' => $t('ui.auth.placeholder.password_current'),
                ],
                'constraints' => [
                    new NotBlank(message: $t('ui.profile.form.validation.current_password_required')),
                    new UserPassword(message: $t('ui.profile.form.validation.current_password_invalid')),
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => $t('ui.profile.form.validation.password_mismatch'),
                'first_options' => [
                    'label' => $t('ui.profile.form.label.new_password'),
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'class' => 'form-control ef-input',
                        'placeholder' => $t('ui.auth.placeholder.password_new'),
                    ],
                    'constraints' => [
                        new NotBlank(message: $t('ui.profile.form.validation.new_password_required')),
                        new Length(
                            min: 8,
                            minMessage: $t('ui.profile.form.validation.new_password_min'),
                        ),
                    ],
                ],
                'second_options' => [
                    'label' => $t('ui.profile.form.label.confirm_new_password'),
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'class' => 'form-control ef-input',
                        'placeholder' => $t('ui.auth.placeholder.password_confirm'),
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
