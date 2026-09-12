<?php

declare(strict_types=1);

namespace App\Enums;

enum PlanItemKind: string
{
    case Recurring = 'recurring';
    case Irregular = 'irregular';
}
