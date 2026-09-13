<?php

declare(strict_types=1);

namespace App\Data;

/**
 * A year of net worth, from the opening position to December (§7.8, NW-01).
 */
final readonly class NetWorthPosition
{
    /**
     * @param  array<int, NetWorthMonth>  $months  month (0-12) to its position
     */
    public function __construct(
        public int $year,
        public array $months,
        /** The latest month anything was actually recorded for, if any. */
        public ?int $latestRecordedMonth,
    ) {}

    public function month(int $month): NetWorthMonth
    {
        return $this->months[$month];
    }

    /**
     * Where the user stands now: the last month they told us about, or the opening
     * position when they have not started.
     */
    public function current(): NetWorthMonth
    {
        return $this->months[$this->latestRecordedMonth ?? 0];
    }

    /**
     * How much changed since last month. Null in the opening position, which has no month
     * before it to compare against.
     */
    public function changeVsPreviousMonth(): ?int
    {
        $month = $this->latestRecordedMonth;

        if ($month === null || $month === 0) {
            return null;
        }

        return $this->month($month)->netCents() - $this->month($month - 1)->netCents();
    }

    public function changeVsStartOfYear(): int
    {
        return $this->current()->netCents() - $this->month(0)->netCents();
    }
}
