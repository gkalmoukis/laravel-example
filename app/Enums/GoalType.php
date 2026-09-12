<?php

declare(strict_types=1);

namespace App\Enums;

enum GoalType: string
{
    case EmergencyFund = 'emergency_fund';
    case Investment = 'investment';
    case YearEndBalance = 'year_end_balance';
    case Purchase = 'purchase';
    case DebtPayoff = 'debt_payoff';
    case Other = 'other';
}
