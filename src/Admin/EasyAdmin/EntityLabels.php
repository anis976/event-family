<?php

declare(strict_types=1);

namespace App\Admin\EasyAdmin;

use App\Contract\EfAdminLabelInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Contracts\Translation\TranslatableInterface;

final class EntityLabels
{
    public static function format(mixed $value, string $empty = '—'): string
    {
        if (null === $value || '' === $value) {
            return $empty;
        }

        if (\is_string($value)) {
            return $value;
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        if (\is_object($value)) {
            if ($value instanceof EfAdminLabelInterface) {
                return $value->getAdminLabel();
            }

            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
        }

        return $empty;
    }

    public static function association(string $property, string $label, string $empty = '—'): AssociationField
    {
        return AssociationField::new($property, $label)
            ->formatValue(static fn (mixed $value): string => self::format($value, $empty))
            ->setFormTypeOption('choice_label', static fn (mixed $entity): string => self::format($entity, $empty));
    }

    /**
     * @param TranslatableInterface|string|false|null $label
     */
    public static function entityFilter(
        string $propertyName,
        TranslatableInterface|string|false|null $label = null,
        string $empty = '—',
    ): EntityFilter {
        return EntityFilter::new($propertyName, $label)
            ->setFormTypeOption('value_type_options', [
                'choice_label' => static fn (mixed $entity): string => self::format($entity, $empty),
            ]);
    }
}
