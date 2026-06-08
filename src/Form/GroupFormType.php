<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Group;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<Group>
 */
final class GroupFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $t = fn (string $id): string => $this->translator->trans($id);

        $builder
            ->add('name', TextType::class, [
                'label' => $t('group.form.name'),
                'attr' => ['class' => 'form-control ef-input', 'placeholder' => $t('group.form.name_placeholder')],
                'constraints' => [
                    new NotBlank(message: $t('group.form.validation.name_required')),
                    new Length(max: 255),
                ],
            ])
            ->add('familyName', TextType::class, [
                'label' => $t('group.form.family_name'),
                'attr' => ['class' => 'form-control ef-input', 'placeholder' => $t('group.form.family_name_placeholder')],
                'constraints' => [
                    new NotBlank(message: $t('group.form.validation.family_name_required')),
                    new Length(max: 255),
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => $t('group.form.description'),
                'attr' => [
                    'class' => 'form-control ef-input ef-groups-form__textarea js-input-count',
                    'rows' => 6,
                    'maxlength' => 500,
                    'placeholder' => $t('group.form.description_placeholder'),
                ],
                'constraints' => [
                    new Length(max: 500, maxMessage: $t('group.form.validation.description_max')),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Group::class,
            'attr' => ['data-turbo' => 'false'],
        ]);
    }
}
