<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Types de messages flash (alignés sur les alertes Bootstrap).
 */
enum FlashType: string
{
    case Success = 'success';
    case Danger = 'danger';
    case Warning = 'warning';
    case Info = 'info';
}
