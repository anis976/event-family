<?php

namespace App;

use App\Util\ParisClock;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        date_default_timezone_set(ParisClock::TIMEZONE);

        parent::boot();
    }
}
