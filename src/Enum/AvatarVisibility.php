<?php

declare(strict_types=1);

namespace App\Enum;

enum AvatarVisibility: string
{
    case Public = 'public';
    case Private = 'private';
}
