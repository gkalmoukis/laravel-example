<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionIssue: string
{
    case NoFinancialYear = 'no_financial_year';
    case CategoryTypeMismatch = 'category_type_mismatch';
    case SubcategoryParentMismatch = 'subcategory_parent_mismatch';
}
