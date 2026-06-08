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
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<Event>
 */
final class EventFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<Group> $memberGroups */
        $memberGroups = $options['member_groups'];

        $t = fn (string $key): string => $this->translator->trans($key);

        $builder
            ->add('relatedGroup', EntityType::class, [
                'class' => Group::class,
                'choices' => $memberGroups,
                'choice_label' => static fn (Group $group): string => $group->getDisplayLabel(),
                'label' => $t('event.form.group'),
                'placeholder' => $t('event.form.group_placeholder'),
                'attr' => ['class' => 'form-select ef-input'],
                'constraints' => [
                    new NotBlank(message: $t('event.form.validation.group_required')),
                ],
            ])
            ->add('title', TextType::class, [
                'label' => $t('event.form.title'),
                'attr' => ['class' => 'form-control ef-input', 'placeholder' => $t('event.form.title_placeholder')],
                'constraints' => [
                    new NotBlank(message: $t('event.form.validation.title_required')),
                    new Length(max: 255),
                ],
            ])
            ->add('kind', EnumType::class, [
                'class' => EventKind::class,
                'label' => $t('event.form.kind'),
                'choice_label' => fn (EventKind $kind): string => $this->translator->trans($kind->label()),
                'attr' => ['class' => 'form-select ef-input'],
            ])
            ->add('startDate', DateTimeType::class, [
                'label' => $t('event.form.start_date'),
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-control ef-input'],
                'constraints' => [
                    new NotBlank(message: $t('event.form.validation.start_required')),
                ],
            ])
            ->add('endDate', DateTimeType::class, [
                'required' => false,
                'label' => $t('event.form.end_date'),
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-control ef-input'],
            ])
            ->add('location', TextType::class, [
                'required' => false,
                'label' => $t('event.form.location'),
                'attr' => ['class' => 'form-control ef-input', 'placeholder' => $t('event.form.location_placeholder')],
                'constraints' => [
                    new Length(max: 255),
                ],
            ])
            ->add('visibility', EnumType::class, [
                'class' => EventVisibility::class,
                'label' => $t('event.form.visibility'),
                'choice_label' => fn (EventVisibility $visibility): string => $this->translator->trans($visibility->label()),
                'attr' => ['class' => 'form-select ef-input'],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => $t('event.form.description'),
                'attr' => [
                    'class' => 'form-control ef-input ef-event__textarea js-input-count',
                    'rows' => 6,
                    'maxlength' => 2000,
                    'placeholder' => $t('event.form.description_placeholder'),
                ],
                'constraints' => [
                    new Length(max: 2000, maxMessage: $t('event.form.validation.description_max')),
                ],
            ])
            ->add('photoCoverFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => $t('event.form.photo_cover'),
                'attr' => [
                    'class' => 'form-control ef-input',
                    'accept' => 'image/jpeg,image/png,image/webp',
                ],
            ])
            ->add('photoDetailFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => $t('event.form.photo_detail'),
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
                    'label' => $t('event.form.remove_cover'),
                ])
                ->add('removePhotoDetail', CheckboxType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => $t('event.form.remove_detail'),
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
