<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The seven things worth interrupting someone about (§8.19).
 *
 * Nothing here is stored or sent anywhere: alerts are worked out on each request from the
 * same figures the screens show, so one disappears the moment its cause is fixed.
 */
enum AlertType: string
{
    case NegativeForecastBalance = 'negative_forecast_balance';
    case ForecastBelowEmergencyFund = 'forecast_below_emergency_fund';
    case TransactionsWithIssues = 'transactions_with_issues';
    case CategoryTypeMismatch = 'category_type_mismatch';
    case BudgetOverrun = 'budget_overrun';
    case IncompleteMonth = 'incomplete_month';
    case GoalOffTrack = 'goal_off_track';

    /**
     * How loudly this asks for attention, lowest first (§8.19).
     *
     * Running out of money comes before spending into the reserve, which comes before
     * figures that are merely wrong, which come before a plan that has drifted. The order
     * is fixed by the specification rather than by the order of the cases.
     */
    public function severity(): int
    {
        return match ($this) {
            self::NegativeForecastBalance => 0,
            self::ForecastBelowEmergencyFund => 1,
            self::TransactionsWithIssues => 2,
            self::CategoryTypeMismatch => 3,
            self::BudgetOverrun => 4,
            self::IncompleteMonth => 5,
            self::GoalOffTrack => 6,
        };
    }

    /**
     * The headline, in plain language: what has happened, not which rule fired (§5.2).
     */
    public function title(): string
    {
        return match ($this) {
            self::NegativeForecastBalance => 'You are forecast to run out of money',
            self::ForecastBelowEmergencyFund => 'You would have to dip into your emergency fund',
            self::TransactionsWithIssues => 'Some transactions need a look',
            self::CategoryTypeMismatch => 'Some transactions are filed the wrong way round',
            self::BudgetOverrun => 'A category has gone over budget',
            self::IncompleteMonth => 'A month is still unfinished',
            self::GoalOffTrack => 'A goal has fallen behind',
        };
    }

    /**
     * One sentence saying which month, which category or how many — the detail that makes
     * the alert worth acting on rather than merely worrying about.
     */
    public function explanation(string $detail): string
    {
        return match ($this) {
            self::NegativeForecastBalance => sprintf('On current figures your balance goes below zero in %s.', $detail),
            self::ForecastBelowEmergencyFund => sprintf('Your forecast balance in %s is lower than what you have set aside.', $detail),
            self::TransactionsWithIssues => sprintf('%s will not be counted in your reports until they are fixed.', $detail),
            self::CategoryTypeMismatch => sprintf('%s are under a category that records money moving the other way.', $detail),
            self::BudgetOverrun => sprintf('%s has spent more than it planned to.', $detail),
            self::IncompleteMonth => sprintf('%s has not been signed off, so its figures are still provisional.', $detail),
            self::GoalOffTrack => sprintf('%s will not reach its target by the date you set.', $detail),
        };
    }

    /**
     * What the single action link says. One per alert, because an alert offering choices
     * is a decision rather than a prompt.
     */
    public function actionLabel(): string
    {
        return match ($this) {
            self::NegativeForecastBalance, self::ForecastBelowEmergencyFund => 'See the forecast',
            self::TransactionsWithIssues, self::CategoryTypeMismatch => 'Review these transactions',
            self::BudgetOverrun => 'Compare with the plan',
            self::IncompleteMonth => 'Finish the month',
            self::GoalOffTrack => 'Open goals',
        };
    }
}
