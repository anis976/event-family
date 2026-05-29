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

/**
 * @extends AbstractType<Group>
 */
final class GroupFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du groupe',
                'attr' => ['class' => 'form-control ef-input', 'placeholder' => 'Ex. Les Dupont'],
                'constraints' => [
                    new NotBlank(message: 'Le nom du groupe est obligatoire.'),
                    new Length(max: 255),
                ],
            ])
            ->add('familyName', TextType::class, [
                'label' => 'Nom de famille',
                'attr' => ['class' => 'form-control ef-input', 'placeholder' => 'Ex. Famille Dupont'],
                'constraints' => [
                    new NotBlank(message: 'Le nom de famille est obligatoire.'),
                    new Length(max: 255),
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control ef-input ef-groups-form__textarea js-input-count',
                    'rows' => 6,
                    'maxlength' => 500,
                    'placeholder' => 'Décris ton groupe…',
                ],
                'constraints' => [
                    new Length(max: 500, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'),
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
