<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class UniqueUserPseudo extends Constraint
{
    public string $message = 'Ce pseudo est déjà utilisé par un autre compte.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
