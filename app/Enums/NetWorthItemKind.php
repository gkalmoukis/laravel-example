<?php

declare(strict_types=1);

namespace App\Enums;

enum NetWorthItemKind: string
{
    case Cash = 'cash';
    case EmergencyFund = 'emergency_fund';
    case Investment = 'investment';
    case OtherAsset = 'other_asset';
    case Debt = 'debt';
}
