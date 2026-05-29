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

    protected function addSuccessFlash(string $message): void
    {
        $this->addFlash(FlashType::Success->value, $message);
    }

    protected function addErrorFlash(string $message): void
    {
        $this->addFlash(FlashType::Danger->value, $message);
    }

    protected function addWarningFlash(string $message): void
    {
        $this->addFlash(FlashType::Warning->value, $message);
    }

    protected function addInfoFlash(string $message): void
    {
        $this->addFlash(FlashType::Info->value, $message);
    }
}
