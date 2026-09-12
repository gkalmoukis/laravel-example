<?php

declare(strict_types=1);

namespace App\Enums;

enum Allocation: string
{
    case LumpSum = 'lump_sum';
    case Spread = 'spread';
}
