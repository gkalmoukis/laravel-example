<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Allocation;
use App\Enums\Frequency;
use App\ValueObjects\Money;
use InvalidArgumentException;

/**
 * Turns a plan item's frequency into the twelve monthly amounts it implies (§7.5).
 *
 * Pure: it reads nothing and writes nothing. The user can edit any month afterwards, so
 * this only ever produces the starting point.
 */
final readonly class BuildPlanSchedule
{
    public const int MONTHS = 12;

    /**
     * @param  list<int>  $customMonths  months ticked by the user, for Custom frequency
     * @return array<int, Money> the amount for each month, keyed 1 to 12
     */
    public function handle(
        Frequency $frequency,
        Money $amount,
        int $startMonth,
        Allocation $allocation = Allocation::LumpSum,
        array $customMonths = [],
    ): array {
        throw_if($startMonth < 1 || $startMonth > self::MONTHS, InvalidArgumentException::class, 'The starting month must be between 1 and 12.');

        // Spread ignores the frequency: the whole annual total is set aside in equal
        // monthly parts regardless of when it is actually paid.
        if ($allocation === Allocation::Spread) {
            return $this->spread($amount);
        }

        return $this->place($amount, $this->monthsFor($frequency, $startMonth, $customMonths));
    }

    /**
     * Splits the annual total across all twelve months so they sum back to it exactly.
     * The remainder lands on the last months, so the extra cents fall at the end of the
     * year rather than the start.
     *
     * @return array<int, Money>
     */
    private function spread(Money $amount): array
    {
        $parts = $amount->allocate(self::MONTHS);

        $schedule = [];

        foreach ($parts as $index => $part) {
            $schedule[$index + 1] = $part;
        }

        return $schedule;
    }

    /**
     * @param  list<int>  $customMonths
     * @return list<int>
     */
    private function monthsFor(Frequency $frequency, int $startMonth, array $customMonths): array
    {
        if ($frequency === Frequency::Once || $frequency === Frequency::Annual) {
            return [$startMonth];
        }

        if ($frequency === Frequency::Monthly) {
            return $this->everyNthMonth($startMonth, 1);
        }

        if ($frequency === Frequency::Quarterly) {
            return $this->everyNthMonth($startMonth, 3);
        }

        if ($frequency === Frequency::SemiAnnual) {
            return $this->everyNthMonth($startMonth, 6);
        }

        return $this->validCustomMonths($customMonths);
    }

    /**
     * @return list<int>
     */
    private function everyNthMonth(int $startMonth, int $step): array
    {
        $months = [];

        for ($month = $startMonth; $month <= self::MONTHS; $month += $step) {
            $months[] = $month;
        }

        return $months;
    }

    /**
     * @param  list<int>  $customMonths
     * @return list<int>
     */
    private function validCustomMonths(array $customMonths): array
    {
        $months = array_values(array_unique(array_filter(
            $customMonths,
            fn (int $month): bool => $month >= 1 && $month <= self::MONTHS,
        )));

        sort($months);

        return $months;
    }

    /**
     * @param  list<int>  $months
     * @return array<int, Money>
     */
    private function place(Money $amount, array $months): array
    {
        $schedule = [];

        for ($month = 1; $month <= self::MONTHS; $month++) {
            $schedule[$month] = in_array($month, $months, true) ? $amount : Money::zero();
        }

        return $schedule;
    }
}
