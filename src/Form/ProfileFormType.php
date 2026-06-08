<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<User>
 */
final class ProfileFormType extends AbstractType
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
                'label' => $t('ui.profile.first_name'),
                'attr' => ['autocomplete' => 'given-name'],
                'constraints' => [
                    new NotBlank(message: $t('ui.profile.form.validation.first_name_required')),
                    new Length(max: 100),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => $t('ui.profile.last_name'),
                'attr' => ['autocomplete' => 'family-name'],
                'constraints' => [
                    new NotBlank(message: $t('ui.profile.form.validation.last_name_required')),
                    new Length(max: 100),
                ],
            ])
            ->add('pseudo', TextType::class, [
                'required' => false,
                'label' => $t('ui.profile.pseudo'),
                'help' => $t('ui.profile.form.validation.pseudo_help'),
                'attr' => [
                    'autocomplete' => 'username',
                    'placeholder' => $t('ui.profile.pseudo_optional'),
                ],
                'constraints' => [
                    new Length(max: 64),
                ],
            ])
            ->add('notifyPrivateMessageEmail', CheckboxType::class, [
                'required' => false,
                'false_values' => [null, '', false, '0', 0],
                'label' => $t('ui.profile.notify_private_message_email'),
                'help' => $t('ui.profile.notify_private_message_email_help'),
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            if (!\is_array($data)) {
                return;
            }

            if (!\array_key_exists('notifyPrivateMessageEmail', $data)) {
                $data['notifyPrivateMessageEmail'] = false;
            }

            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'validation_groups' => ['Default', 'Profile'],
        ]);
    }
}
