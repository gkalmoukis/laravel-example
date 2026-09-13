<?php

declare(strict_types=1);

namespace App\Enums;

enum VarianceStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Over = 'over';
    case NoPlan = 'no_plan';

    /**
     * How loudly a category is asking for attention, lowest first (CMP-06).
     *
     * Comparison screens sort by this so the categories that went wrong are at the top,
     * which is the whole reason someone opens that screen.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Over => 0,
            self::Warning => 1,
            self::Ok => 2,
            self::NoPlan => 3,
        };
    }

    /**
     * What the badge says. An income category that came up short has not gone "over
     * budget" — it has missed a target, and saying so wrongly would read as nonsense
     * (§5.2).
     */
    public function label(TransactionType $type): string
    {
        if ($type === TransactionType::Income) {
            return match ($this) {
                self::Ok => 'On target',
                self::Warning => 'Slightly under',
                self::Over => 'Under target',
                self::NoPlan => 'No plan',
            };
        }

        return match ($this) {
            self::Ok => 'Within budget',
            self::Warning => 'Slightly over',
            self::Over => 'Over budget',
            self::NoPlan => 'No plan',
        };
    }
}
