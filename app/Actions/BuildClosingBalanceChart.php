<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FinancialYear;
use Carbon\CarbonImmutable;

/**
 * Closing balance, plan against actual against forecast, for the dashboard (DASH-04, CF-02).
 *
 * The actual line stops where the record stops rather than dropping to zero: a month
 * nobody has reached is missing, not empty. Every figure is integer cents — the interface
 * formats, and a chart that quietly rounded would disagree with the table beside it.
 */
final readonly class BuildClosingBalanceChart
{
    private const int MONTHS = 12;

    public function __construct(private CalculateCashFlow $cashFlow) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(FinancialYear $financialYear, CarbonImmutable $today): array
    {
        $flow = $this->cashFlow->handle($financialYear, $today);

        $points = [];

        for ($month = 1; $month <= self::MONTHS; $month++) {
            $line = $flow->month($month);

            $points[] = [
                'month' => $month,
                'plannedCents' => $line->planned->closingCents,
                'forecastCents' => $line->forecast->closingCents,
                'actualCents' => $line->actual?->closingCents,
            ];
        }

        return $points;
    }
}
