<?php

declare(strict_types=1);

namespace App\Controller\Admin\Crud;

use App\Admin\EasyAdmin\AdminDateFormatter;
use App\Admin\EasyAdmin\EntityLabels;
use App\Util\ParisClock;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractAdminCrudController extends AbstractCrudController
{
    private TranslatorInterface $adminTranslator;

    private AdminDateFormatter $adminDateFormatter;

    #[Required]
    public function setAdminTranslator(TranslatorInterface $adminTranslator): void
    {
        $this->adminTranslator = $adminTranslator;
    }

    #[Required]
    public function setAdminDateFormatter(AdminDateFormatter $adminDateFormatter): void
    {
        $this->adminDateFormatter = $adminDateFormatter;
    }

    protected function adminDateTimeField(string $property, string $label, string $pageName, bool $withTime = true): DateTimeField
    {
        $shortYear = Crud::PAGE_INDEX === $pageName;
        $format = $withTime
            ? ($shortYear ? AdminDateFormatter::PATTERN_DATETIME_SHORT : AdminDateFormatter::PATTERN_DATETIME)
            : ($shortYear ? AdminDateFormatter::PATTERN_DATE_SHORT : AdminDateFormatter::PATTERN_DATE);

        return DateTimeField::new($property, $label)
            ->setTimezone(ParisClock::TIMEZONE)
            ->setFormat($format);
    }

    protected function formatAdminDateTime(\DateTimeInterface $date, bool $shortYear = false): string
    {
        return $this->adminDateFormatter->formatDateTime($date, $shortYear);
    }

    protected function setIndexSectionTitle(Crud $crud, string $menuTranslationKey): Crud
    {
        return $crud->setPageTitle(Crud::PAGE_INDEX, $this->t($menuTranslationKey));
    }

    /**
     * @param array<string, int|float|string> $parameters
     */
    protected function t(string $id, array $parameters = []): string
    {
        return $this->adminTranslator->trans($id, $parameters, 'messages');
    }

    protected function notAvailable(): string
    {
        return $this->t('admin.crud.common.not_available');
    }

    protected function entityAssociation(string $property, string $label): AssociationField
    {
        return EntityLabels::association($property, $label, $this->notAvailable());
    }

    protected function entityFilter(string $property, string $label): EntityFilter
    {
        return EntityLabels::entityFilter($property, $label, $this->notAvailable());
    }

    protected function translateDomainException(\DomainException $exception): \DomainException
    {
        return new \DomainException($this->t($exception->getMessage()));
    }
}
