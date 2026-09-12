<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\EntrySource;
use App\Enums\TransactionIssue;
use App\Enums\TransactionType;
use App\ValueObjects\Money;
use Carbon\CarbonInterface;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Something that actually happened: money in or out on a given day.
 *
 * Transactions are never linked to a financial year by key. A transaction belongs to
 * whichever year contains its date, so recording one before that year exists is allowed
 * and starts counting the moment the year is created.
 *
 * @property-read int $id
 * @property-read string $user_id
 * @property-read TransactionType $type
 * @property-read CarbonInterface $occurred_on
 * @property-read Money $amount_cents
 * @property-read int $category_id
 * @property-read int|null $subcategory_id
 * @property-read int|null $account_id
 * @property-read string $description
 * @property-read string|null $notes
 * @property-read EntrySource $entry_source
 * @property-read int|null $entry_duration_ms
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $user
 * @property-read Category $category
 * @property-read Category|null $subcategory
 * @property-read Account|null $account
 */
#[Fillable([
    'type',
    'occurred_on',
    'amount_cents',
    'category_id',
    'subcategory_id',
    'account_id',
    'description',
    'notes',
    'entry_source',
    'entry_duration_ms',
])]
final class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /**
     * Adds the three issue flags as derived columns.
     *
     * Nothing is stored: an issue disappears the moment its cause is fixed, so creating
     * the missing year un-flags every transaction that was waiting for it (TXV-04).
     *
     * @param  Builder<self>|null  $query
     * @return Builder<self>
     */
    public static function withIssues(?Builder $query = null): Builder
    {
        return ($query ?? self::query())
            // No financial year contains this transaction's date.
            ->selectRaw('transactions.*')
            ->selectRaw(sprintf(
                'NOT EXISTS (%s) as has_no_financial_year',
                'select 1 from financial_years'
                .' where financial_years.user_id = transactions.user_id'
                .' and financial_years.year = year(transactions.occurred_on)',
            ))
            // The category records a direction that disagrees with the transaction's.
            ->selectRaw(sprintf(
                'EXISTS (%s) as has_category_type_mismatch',
                'select 1 from categories'
                .' where categories.id = transactions.category_id'
                .' and categories.type <> transactions.type',
            ))
            // The subcategory has been moved out from under the chosen category.
            ->selectRaw(sprintf(
                'EXISTS (%s) as has_subcategory_parent_mismatch',
                'select 1 from categories'
                .' where categories.id = transactions.subcategory_id'
                .' and (categories.parent_id is null'
                .' or categories.parent_id <> transactions.category_id)',
            ));
    }

    /**
     * Only transactions with no issue at all.
     *
     * Every figure in the calculation rules reads through this: a flagged transaction is
     * listed and explained, but never counted, because counting a number the user has
     * not finished correcting would be worse than leaving it out (TXV-05).
     *
     * @param  Builder<self>|null  $query
     * @return Builder<self>
     */
    public static function valid(?Builder $query = null): Builder
    {
        return ($query ?? self::query())
            ->whereExists(fn (QueryBuilder $sub): QueryBuilder => $sub->selectRaw('1')
                ->from('financial_years')
                ->whereColumn('financial_years.user_id', 'transactions.user_id')
                ->whereRaw('financial_years.year = year(transactions.occurred_on)'))
            ->whereExists(fn (QueryBuilder $sub): QueryBuilder => $sub->selectRaw('1')
                ->from('categories')
                ->whereColumn('categories.id', 'transactions.category_id')
                ->whereColumn('categories.type', 'transactions.type'))
            ->where(fn (Builder $outer): Builder => $outer
                ->whereNull('transactions.subcategory_id')
                ->orWhereExists(fn (QueryBuilder $sub): QueryBuilder => $sub->selectRaw('1')
                    ->from('categories')
                    ->whereColumn('categories.id', 'transactions.subcategory_id')
                    ->whereColumn('categories.parent_id', 'transactions.category_id')));
    }

    /**
     * The opposite of valid(): everything the user still has to put right.
     *
     * @param  Builder<self>|null  $query
     * @return Builder<self>
     */
    public static function flagged(?Builder $query = null): Builder
    {
        return ($query ?? self::query())
            ->where(fn (Builder $outer): Builder => $outer
                ->whereNotExists(fn (QueryBuilder $sub): QueryBuilder => $sub->selectRaw('1')
                    ->from('financial_years')
                    ->whereColumn('financial_years.user_id', 'transactions.user_id')
                    ->whereRaw('financial_years.year = year(transactions.occurred_on)'))
                ->orWhereExists(fn (QueryBuilder $sub): QueryBuilder => $sub->selectRaw('1')
                    ->from('categories')
                    ->whereColumn('categories.id', 'transactions.category_id')
                    ->whereColumn('categories.type', '<>', 'transactions.type'))
                ->orWhereExists(fn (QueryBuilder $sub): QueryBuilder => $sub->selectRaw('1')
                    ->from('categories')
                    ->whereColumn('categories.id', 'transactions.subcategory_id')
                    ->whereRaw('(categories.parent_id is null or categories.parent_id <> transactions.category_id)')));
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'user_id' => 'string',
            'type' => TransactionType::class,
            'occurred_on' => 'date',
            'amount_cents' => MoneyCast::class,
            'category_id' => 'integer',
            'subcategory_id' => 'integer',
            'account_id' => 'integer',
            'description' => 'string',
            'notes' => 'string',
            'entry_source' => EntrySource::class,
            'entry_duration_ms' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * The issues this transaction currently has, worked out from the data rather than
     * read from a column.
     *
     * @return list<TransactionIssue>
     */
    public function issues(): array
    {
        $issues = [];

        $hasYear = FinancialYear::query()
            ->where('user_id', $this->user_id)
            ->where('year', $this->occurred_on->year)
            ->exists();

        if (! $hasYear) {
            $issues[] = TransactionIssue::NoFinancialYear;
        }

        if ($this->category->type !== $this->type) {
            $issues[] = TransactionIssue::CategoryTypeMismatch;
        }

        if ($this->subcategory !== null && $this->subcategory->parent_id !== $this->category_id) {
            $issues[] = TransactionIssue::SubcategoryParentMismatch;
        }

        return $issues;
    }

    public function isFlagged(): bool
    {
        return $this->issues() !== [];
    }
}
