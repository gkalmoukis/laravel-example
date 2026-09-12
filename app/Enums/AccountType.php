<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Card = 'card';
    case Other = 'other';
}
