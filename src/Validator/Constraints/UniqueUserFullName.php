<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class UniqueUserFullName extends Constraint
{
    public string $message = 'Ce prénom et ce nom sont déjà associés à un autre compte.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
