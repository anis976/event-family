<?php

declare(strict_types=1);

namespace App\Enum;

enum EventPhotoSlot: string
{
    case Cover = 'cover';
    case Detail = 'detail';
}
