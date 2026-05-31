<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @extends AbstractType<null>
 */
final class ContactFormType extends AbstractType
{
    public const int MESSAGE_MIN_LENGTH = 20;

    public const int MESSAGE_MAX_LENGTH = 2000;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr' => [
                    'class' => 'form-control ef-input ef-contact__textarea js-input-count',
                    'rows' => 6,
                    'maxlength' => self::MESSAGE_MAX_LENGTH,
                    'placeholder' => 'Décris ta demande en détail…',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le message est obligatoire.'),
                    new Length(
                        max: self::MESSAGE_MAX_LENGTH,
                        maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères.',
                    ),
                    new Callback($this->validateMessage(...)),
                ],
            ])
            ->add('companyWebsite', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Site web de l\'entreprise',
                'attr' => [
                    'class' => 'ef-contact__honeypot',
                    'autocomplete' => 'off',
                    'tabindex' => '-1',
                ],
            ])
            ->add('_startedAt', HiddenType::class, [
                'mapped' => false,
                'data' => (string) time(),
            ]);

        if ($options['recaptcha_enabled']) {
            $builder->add('recaptchaToken', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'js-contact-recaptcha-token'],
            ]);
        }

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options): void {
            if (!$options['recaptcha_enabled']) {
                return;
            }

            $data = $event->getData();
            if (!\is_array($data)) {
                return;
            }

            if (!\array_key_exists('recaptchaToken', $data)) {
                $data['recaptchaToken'] = '';
                $event->setData($data);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'class' => 'ef-contact__form',
                'data-turbo' => 'false',
            ],
            'recaptcha_enabled' => false,
        ]);

        $resolver->setAllowedTypes('recaptcha_enabled', 'bool');
    }

    public function validateMessage(?string $value, ExecutionContextInterface $context): void
    {
        $trimmed = trim((string) $value);

        if ('' === $trimmed) {
            return;
        }

        if (mb_strlen($trimmed) < self::MESSAGE_MIN_LENGTH) {
            $context->buildViolation(sprintf(
                'Décris ta demande en au moins %d caractères (évite les messages du type « salut » ou « bonjour » seuls).',
                self::MESSAGE_MIN_LENGTH,
            ))->addViolation();
        }
    }
}
