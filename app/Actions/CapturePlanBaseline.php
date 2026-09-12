<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TransactionType;
use App\Models\FinancialYear;
use App\Models\PlanItem;

/**
 * Freezes the plan as it stands, so later drift can be measured against what was
 * originally intended rather than against the plan as edited since (FC-06).
 *
 * Captured when setup finishes, and re-captured only when the user deliberately asks.
 */
final readonly class CapturePlanBaseline
{
    public const int MONTHS = 12;

    public function __construct(private UpdateOpeningPosition $openingPosition) {}

    public function handle(FinancialYear $financialYear): FinancialYear
    {
        $financialYear->forceFill([
            'baseline' => $this->build($financialYear),
            'baseline_captured_at' => now(),
        ])->save();

        return $financialYear;
    }

    /**
     * Planned income and expenses per month, the closing balance they imply, and the
     * annual totals.
     *
     * @return array<string, mixed>
     */
    public function build(FinancialYear $financialYear): array
    {
        $income = $this->monthlyTotals($financialYear, TransactionType::Income);
        $expenses = $this->monthlyTotals($financialYear, TransactionType::Expense);

        $openingBalance = $this->openingPosition->openingLiquidBalance($financialYear)->cents;

        $closingBalances = [];
        $balance = $openingBalance;

        for ($month = 1; $month <= self::MONTHS; $month++) {
            // Signed: a plan can spend more than it earns, and the balance may go below
            // zero, which is exactly what the forecast warns about.
            $balance += $income[$month] - $expenses[$month];
            $closingBalances[$month] = $balance;
        }

        return [
            'opening_balance_cents' => $openingBalance,
            'monthly_income_cents' => $income,
            'monthly_expenses_cents' => $expenses,
            'monthly_closing_balance_cents' => $closingBalances,
            'annual_income_cents' => array_sum($income),
            'annual_expenses_cents' => array_sum($expenses),
            'annual_savings_cents' => array_sum($income) - array_sum($expenses),
            'year_end_balance_cents' => $closingBalances[self::MONTHS],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function monthlyTotals(FinancialYear $financialYear, TransactionType $type): array
    {
        $totals = array_fill_keys(range(1, self::MONTHS), 0);

        $rows = PlanItem::query()
            ->where('financial_year_id', $financialYear->id)
            ->where('type', $type)
            ->join('plan_item_amounts', 'plan_item_amounts.plan_item_id', '=', 'plan_items.id')
            ->groupBy('plan_item_amounts.month')
            ->selectRaw('plan_item_amounts.month as month, sum(plan_item_amounts.amount_cents) as total')
            ->pluck('total', 'month');

        foreach ($rows as $month => $total) {
            if (is_numeric($month) && is_numeric($total)) {
                $totals[(int) $month] = (int) $total;
            }
        }

        return $totals;
    }
}
