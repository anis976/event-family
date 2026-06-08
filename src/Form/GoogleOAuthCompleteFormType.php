<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<User>
 */
final class GoogleOAuthCompleteFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $t = fn (string $id): string => $this->translator->trans($id);

        $builder
            ->add('firstName', TextType::class, [
                'label' => $t('ui.auth.field.first_name'),
                'constraints' => [
                    new NotBlank(message: $t('ui.auth.form.validation.first_name_required')),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => $t('ui.auth.field.last_name'),
                'constraints' => [
                    new NotBlank(message: $t('ui.auth.form.validation.last_name_required')),
                ],
            ])
            ->add('pseudo', TextType::class, [
                'required' => false,
                'label' => $t('ui.auth.field.pseudo'),
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
            'validation_groups' => ['Default', 'Profile'],
        ]);
    }
}
