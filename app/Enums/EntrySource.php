<?php

declare(strict_types=1);

namespace App\Enums;

enum EntrySource: string
{
    case QuickAdd = 'quick_add';
    case Form = 'form';
    case Duplicate = 'duplicate';
}
