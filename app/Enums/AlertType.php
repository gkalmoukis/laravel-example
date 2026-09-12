<?php

declare(strict_types=1);

namespace App\Enums;

enum AlertType: string
{
    case TransactionsWithIssues = 'transactions_with_issues';
    case CategoryTypeMismatch = 'category_type_mismatch';
    case BudgetOverrun = 'budget_overrun';
    case IncompleteMonth = 'incomplete_month';
    case GoalOffTrack = 'goal_off_track';
    case ForecastBelowEmergencyFund = 'forecast_below_emergency_fund';
    case NegativeForecastBalance = 'negative_forecast_balance';
}
