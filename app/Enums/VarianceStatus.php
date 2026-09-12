<?php

declare(strict_types=1);

namespace App\Enums;

enum VarianceStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Over = 'over';
    case NoPlan = 'no_plan';
}
