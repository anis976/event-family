<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

final class StaffPrivateMessageFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $t = fn (string $id): string => $this->translator->trans($id);

        $builder
            ->add('recipientQuery', TextType::class, [
                'label' => 'ui.messages.staff_private.recipient_label',
                'attr' => [
                    'placeholder' => $t('ui.messages.staff_private.recipient_placeholder'),
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: $t('ui.messages.staff_private.recipient_required')),
                    new Assert\Length(max: 180, maxMessage: $t('ui.messages.staff_private.recipient_max')),
                ],
            ])
            ->add('noticeVariant', ChoiceType::class, [
                'label' => 'ui.messages.staff_private.variant_label',
                'choices' => $options['notice_variant_choices'],
                'constraints' => [
                    new Assert\NotBlank(message: $t('ui.messages.staff_private.variant_required')),
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'ui.messages.staff_private.content_label',
                'attr' => [
                    'rows' => 4,
                    'placeholder' => $t('ui.messages.staff_private.content_placeholder'),
                    'style' => 'resize: none',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: $t('ui.messages.form.content_required')),
                    new Assert\Length(max: 5000, maxMessage: $t('ui.messages.form.content_max')),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'staff_private_message',
        ]);
        $resolver->setRequired(['notice_variant_choices']);
        $resolver->setAllowedTypes('notice_variant_choices', 'array');
    }
}
