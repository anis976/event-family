<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ForgotPasswordFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $t = fn (string $id): string => $this->translator->trans($id);

        $builder->add('email', EmailType::class, [
            'label' => $t('ui.auth.field.email'),
            'attr' => [
                'autocomplete' => 'email',
                'placeholder' => $t('ui.auth.placeholder.email'),
                'class' => 'form-control form-control-lg',
            ],
            'constraints' => [
                new NotBlank(message: $t('ui.auth.form.validation.email_required')),
                new Email(message: $t('ui.auth.form.validation.email_invalid')),
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
