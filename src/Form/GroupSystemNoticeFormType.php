<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

final class GroupSystemNoticeFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $t = fn (string $id): string => $this->translator->trans($id);

        $builder->add('content', TextareaType::class, [
            'label' => $t('message.form.system_label'),
            'attr' => [
                'class' => 'form-control ef-input',
                'rows' => 6,
                'placeholder' => $t('message.form.system_placeholder'),
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
            'csrf_token_id' => 'group_system_notice',
        ]);
    }
}
