<?php

declare(strict_types=1);

namespace App\Enum;

enum PlatformNoticeVariant: string
{
    case System = 'system';
    case EventFamily = 'eventfamily';
}
