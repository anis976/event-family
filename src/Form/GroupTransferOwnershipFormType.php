<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

final class GroupTransferOwnershipFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $t = fn (string $id): string => $this->translator->trans($id);

        $builder
            ->add('becomeModerator', CheckboxType::class, [
                'label' => $t('ui.groups.transfer.become_moderator'),
                'required' => false,
            ])
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

    public function getBlockPrefix(): string
    {
        return 'transfer_ownership';
    }
}
