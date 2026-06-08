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
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<User>
 */
final class RegistrationFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $t = fn (string $id): string => $this->translator->trans($id);

        $builder
            ->add('email', EmailType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => $t('ui.auth.placeholder.email'),
                    'autocomplete' => 'email',
                ],
                'constraints' => [
                    new NotBlank(message: $t('ui.auth.form.validation.email_required')),
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => $t('ui.auth.field.first_name'),
                    'autocomplete' => 'given-name',
                ],
                'constraints' => [
                    new NotBlank(message: $t('ui.auth.form.validation.first_name_required')),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => $t('ui.auth.field.last_name'),
                    'autocomplete' => 'family-name',
                ],
                'constraints' => [
                    new NotBlank(message: $t('ui.auth.form.validation.last_name_required')),
                ],
            ])
            ->add('pseudo', TextType::class, [
                'required' => false,
                'label' => false,
                'attr' => [
                    'placeholder' => $t('ui.auth.form.placeholder.pseudo_optional'),
                    'autocomplete' => 'username',
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => $t('ui.auth.form.validation.password_mismatch'),
                'first_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => $t('ui.auth.placeholder.password_new'),
                        'autocomplete' => 'new-password',
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
                    'label' => false,
                    'attr' => [
                        'placeholder' => $t('ui.auth.placeholder.password_confirm'),
                        'autocomplete' => 'new-password',
                    ],
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => false,
                'constraints' => [
                    new IsTrue(message: $t('ui.auth.form.validation.terms_required')),
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
