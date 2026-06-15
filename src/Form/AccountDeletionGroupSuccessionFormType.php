<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Group;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @phpstan-type SuccessionGroupConfig array{
 *     group: Group,
 *     successors: list<User>,
 *     hasOtherMembers: bool,
 * }
 */
final class AccountDeletionGroupSuccessionFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $t = fn (string $id): string => $this->translator->trans($id);

        /** @var list<SuccessionGroupConfig> $groups */
        $groups = $options['groups'];

        foreach ($groups as $config) {
            $group = $config['group'];
            $groupId = (string) $group->getId();
            $hasOtherMembers = $config['hasOtherMembers'];

            if ($hasOtherMembers) {
                $successorChoices = [];
                foreach ($config['successors'] as $successor) {
                    $successorChoices[$successor->getAdminLabel()] = $successor->getId();
                }

                $builder
                    ->add('successor_'.$groupId, ChoiceType::class, [
                        'label' => $t('ui.profile.delete_blocked.successor_label'),
                        'choices' => $successorChoices,
                        'placeholder' => $t('ui.profile.delete_blocked.successor_placeholder'),
                        'mapped' => false,
                        'constraints' => [
                            new NotBlank(message: $t('ui.profile.delete_blocked.successor_required')),
                        ],
                    ])
                    ->add('become_moderator_'.$groupId, CheckboxType::class, [
                        'label' => $t('ui.groups.transfer.become_moderator'),
                        'required' => false,
                        'mapped' => false,
                    ]);
            } else {
                $builder->add('dissolve_'.$groupId, CheckboxType::class, [
                    'label' => $t('ui.profile.delete_blocked.dissolve_group'),
                    'mapped' => false,
                    'constraints' => [
                        new IsTrue(message: $t('ui.profile.delete_blocked.dissolve_required')),
                    ],
                ]);
            }
        }

        $builder->add('currentPassword', PasswordType::class, [
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
            'groups' => [],
            'attr' => [
                'novalidate' => 'novalidate',
                'data-turbo' => 'false',
            ],
        ]);

        $resolver->setAllowedTypes('groups', 'array');
    }
}
