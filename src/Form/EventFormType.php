<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Event;
use App\Entity\Group;
use App\Enum\EventKind;
use App\Enum\EventVisibility;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<Event>
 */
final class EventFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<Group> $memberGroups */
        $memberGroups = $options['member_groups'];

        $builder
            ->add('relatedGroup', EntityType::class, [
                'class' => Group::class,
                'choices' => $memberGroups,
                'choice_label' => static fn (Group $group): string => $group->getDisplayLabel(),
                'label' => 'Groupe',
                'placeholder' => 'Choisir un groupe…',
                'attr' => ['class' => 'form-select ef-input'],
                'constraints' => [
                    new NotBlank(message: 'Le groupe est obligatoire.'),
                ],
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['class' => 'form-control ef-input', 'placeholder' => 'Ex. Mariage de Julie & Marc'],
                'constraints' => [
                    new NotBlank(message: 'Le titre est obligatoire.'),
                    new Length(max: 255),
                ],
            ])
            ->add('kind', EnumType::class, [
                'class' => EventKind::class,
                'label' => 'Type',
                'choice_label' => static fn (EventKind $kind): string => $kind->label(),
                'attr' => ['class' => 'form-select ef-input'],
            ])
            ->add('startDate', DateTimeType::class, [
                'label' => 'Date et heure de début',
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-control ef-input'],
                'constraints' => [
                    new NotBlank(message: 'La date de début est obligatoire.'),
                ],
            ])
            ->add('endDate', DateTimeType::class, [
                'required' => false,
                'label' => 'Date et heure de fin (optionnel)',
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-control ef-input'],
            ])
            ->add('location', TextType::class, [
                'required' => false,
                'label' => 'Lieu',
                'attr' => ['class' => 'form-control ef-input', 'placeholder' => 'Ex. Château de Versailles'],
                'constraints' => [
                    new Length(max: 255),
                ],
            ])
            ->add('visibility', EnumType::class, [
                'class' => EventVisibility::class,
                'label' => 'Visibilité',
                'choice_label' => static fn (EventVisibility $visibility): string => $visibility->label(),
                'attr' => ['class' => 'form-select ef-input'],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control ef-input ef-event__textarea js-input-count',
                    'rows' => 6,
                    'maxlength' => 2000,
                    'placeholder' => 'Décris l\'événement…',
                ],
                'constraints' => [
                    new Length(max: 2000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('photoCoverFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Photo de couverture (optionnelle)',
                'attr' => [
                    'class' => 'form-control ef-input',
                    'accept' => 'image/jpeg,image/png,image/webp',
                ],
            ])
            ->add('photoDetailFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Photo lieu / détail (optionnelle)',
                'attr' => [
                    'class' => 'form-control ef-input',
                    'accept' => 'image/jpeg,image/png,image/webp',
                ],
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($options): void {
            $eventData = $event->getData();
            if (!$eventData instanceof Event) {
                return;
            }

            $preselectedGroup = $options['preselected_group'];
            if (null !== $preselectedGroup && null === $eventData->getRelatedGroup()) {
                $eventData->setRelatedGroup($preselectedGroup);
            }
        });

        if ($options['allow_remove_photo']) {
            $builder
                ->add('removePhotoCover', CheckboxType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Supprimer la photo de couverture',
                ])
                ->add('removePhotoDetail', CheckboxType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Supprimer la photo lieu / détail',
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
            'attr' => ['data-turbo' => 'false'],
            'member_groups' => [],
            'preselected_group' => null,
            'allow_remove_photo' => false,
        ]);

        $resolver->setAllowedTypes('member_groups', 'array');
        $resolver->setAllowedTypes('preselected_group', ['null', Group::class]);
    }
}
