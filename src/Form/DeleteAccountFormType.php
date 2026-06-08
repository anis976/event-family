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
use Symfony\Contracts\Translation\TranslatorInterface;

final class DeleteAccountFormType extends AbstractType
{
    public const string CONFIRM_PHRASE = 'SUPPRIMER';

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
                    'class' => 'form-control ef-input',
                    'placeholder' => $t('ui.auth.placeholder.password_current'),
                ],
                'constraints' => [
                    new NotBlank(message: $t('ui.profile.form.validation.current_password_required')),
                    new UserPassword(message: $t('ui.profile.form.validation.current_password_invalid')),
                ],
            ])
            ->add('confirmPhrase', TextType::class, [
                'label' => $t('ui.profile.form.label.confirm_phrase'),
                'mapped' => false,
                'help' => $t('ui.profile.form.label.confirm_phrase_help'),
                'attr' => [
                    'class' => 'form-control ef-input',
                    'placeholder' => self::CONFIRM_PHRASE,
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new NotBlank(message: $t('ui.profile.form.validation.confirm_phrase_required')),
                    new EqualTo(
                        value: self::CONFIRM_PHRASE,
                        message: $t('ui.profile.form.validation.confirm_phrase_exact'),
                    ),
                ],
            ])
            ->add('agreeDeletion', CheckboxType::class, [
                'label' => $t('ui.profile.form.label.agree_deletion'),
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: $t('ui.profile.form.validation.agree_deletion')),
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
