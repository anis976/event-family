<?php

declare(strict_types=1);

namespace App\Entity\Trait;

trait AdminLabelTrait
{
    public function __toString(): string
    {
        return $this->getAdminLabel();
    }
}
