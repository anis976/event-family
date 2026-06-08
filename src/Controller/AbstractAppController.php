<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\FlashType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Contrôleur de base : messages flash cohérents (présents et futurs).
 */
abstract class AbstractAppController extends AbstractController
{
    private TranslatorInterface $translator;

    #[Required]
    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    /**
     * Symfony 8 n'expose plus trans() sur AbstractController.
     *
     * @param array<string, mixed> $parameters
     */
    protected function trans(string $id, array $parameters = [], ?string $domain = 'messages', ?string $locale = null): string
    {
        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    /**
     * @param array<string, int|float|string> $parameters
     */
    protected function addSuccessFlash(string $message, array $parameters = []): void
    {
        $this->addFlash(FlashType::Success->value, $this->flashMessage($message, $parameters));
    }

    /**
     * @param array<string, int|float|string> $parameters
     */
    protected function addErrorFlash(string $message, array $parameters = []): void
    {
        $this->addFlash(FlashType::Danger->value, $this->flashMessage($message, $parameters));
    }

    /**
     * @param array<string, int|float|string> $parameters
     */
    protected function addWarningFlash(string $message, array $parameters = []): void
    {
        $this->addFlash(FlashType::Warning->value, $this->flashMessage($message, $parameters));
    }

    /**
     * @param array<string, int|float|string> $parameters
     */
    protected function addInfoFlash(string $message, array $parameters = []): void
    {
        $this->addFlash(FlashType::Info->value, $this->flashMessage($message, $parameters));
    }

    /**
     * Traduit les clés messages (flash.*, ui.*, event.*, group.*, etc.) ; laisse le texte tel quel s'il n'y a pas de traduction.
     *
     * @param array<string, int|float|string> $parameters
     */
    private function flashMessage(string $message, array $parameters = []): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/', $message)) {
            return $message;
        }

        $translated = $this->translator->trans($message, $parameters, 'messages');

        return $translated !== $message ? $translated : $message;
    }
}
