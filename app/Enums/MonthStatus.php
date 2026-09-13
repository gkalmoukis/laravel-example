<?php

declare(strict_types=1);

namespace App\Enums;

enum MonthStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Complete = 'complete';

    /**
     * What a month in this state contributes to the forecast (FC-02).
     *
     * A finished month is final and speaks for itself; an unfinished one keeps its plan
     * but is not allowed to ignore what has already happened (§7.1).
     */
    public function forecastSource(): string
    {
        return match ($this) {
            self::Complete => 'Actual',
            self::InProgress => 'Plan + actual',
            self::NotStarted => 'Plan',
        };
    }
}
