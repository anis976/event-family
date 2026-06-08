<?php

declare(strict_types=1);

namespace App\Contract;

/**
 * Libellé affichable dans EasyAdmin (listes, filtres, associations).
 */
interface EfAdminLabelInterface extends \Stringable
{
    public function getAdminLabel(): string;
}
