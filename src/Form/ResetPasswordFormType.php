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
use Symfony\Contracts\Translation\TranslatorInterface;

final class ResetPasswordFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $t = fn (string $id): string => $this->translator->trans($id);

        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'invalid_message' => $t('ui.auth.form.validation.password_mismatch'),
            'first_options' => [
                'label' => $t('ui.auth.field.password'),
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => 'form-control form-control-lg',
                    'placeholder' => $t('ui.auth.placeholder.password_new'),
                ],
                'constraints' => [
                    new NotBlank(message: $t('ui.auth.form.validation.password_required')),
                    new Length(
                        min: 8,
                        minMessage: $t('ui.auth.form.validation.password_min'),
                    ),
                ],
            ],
            'second_options' => [
                'label' => $t('ui.auth.field.confirm_password'),
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => 'form-control form-control-lg',
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
