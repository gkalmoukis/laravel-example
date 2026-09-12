<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionIssue: string
{
    case NoFinancialYear = 'no_financial_year';
    case CategoryTypeMismatch = 'category_type_mismatch';
    case SubcategoryParentMismatch = 'subcategory_parent_mismatch';

    /**
     * Why the transaction is flagged, in the words the list shows (TXV-05).
     *
     * Each says what is wrong and implies the fix, because a badge the user cannot act on
     * is just noise.
     */
    public function reason(): string
    {
        return match ($this) {
            self::NoFinancialYear => "You don't have a plan for this year yet, so this won't count in reports.",
            self::CategoryTypeMismatch => 'Its category records money moving the other way.',
            self::SubcategoryParentMismatch => 'Its subcategory has been moved under a different category.',
        };
    }
}
