<?php

declare(strict_types=1);

namespace App\Enums;

enum Frequency: string
{
    case Once = 'once';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnual = 'semi_annual';
    case Annual = 'annual';
    case Custom = 'custom';
}
