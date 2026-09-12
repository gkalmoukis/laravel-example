<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;
use Throwable;

/**
 * The transaction list's filters, read from the query string (TXL-01, TXL-02).
 *
 * These are coerced rather than validated. The filter state lives in the address so it can
 * be linked to and bookmarked, and a report that deep-links into the list builds that
 * address itself — so a value that makes no sense is dropped rather than turned into an
 * error page the user cannot navigate away from. Nothing here can widen what the user
 * sees: the query is scoped to them first, and every filter only narrows it.
 */
final class IndexTransactionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    public function from(): ?CarbonImmutable
    {
        return $this->dateFilter('from');
    }

    public function to(): ?CarbonImmutable
    {
        return $this->dateFilter('to');
    }

    /**
     * A month only means something inside a year, so it carries one: whichever year the
     * list is showing, falling back to the year the user is currently living in.
     */
    public function month(): ?int
    {
        $month = $this->integer('month');

        return $month >= 1 && $month <= 12 ? $month : null;
    }

    public function year(): int
    {
        $year = $this->integer('year');

        return $year > 0 ? $year : CarbonImmutable::now()->year;
    }

    public function type(): ?TransactionType
    {
        return TransactionType::tryFrom($this->string('type')->value());
    }

    public function categoryId(): ?int
    {
        return $this->positiveInteger('category_id');
    }

    public function subcategoryId(): ?int
    {
        return $this->positiveInteger('subcategory_id');
    }

    public function accountId(): ?int
    {
        return $this->positiveInteger('account_id');
    }

    public function minAmount(): ?Money
    {
        return $this->money('min_amount');
    }

    public function maxAmount(): ?Money
    {
        return $this->money('max_amount');
    }

    public function search(): ?string
    {
        $search = mb_trim($this->string('q')->value());

        return $search === '' ? null : mb_substr($search, 0, 255);
    }

    public function onlyIssues(): bool
    {
        return $this->boolean('issues');
    }

    /**
     * What the page echoes back into its own inputs, so the form and the address agree.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'from' => $this->from()?->toDateString(),
            'to' => $this->to()?->toDateString(),
            'month' => $this->month(),
            'year' => $this->year(),
            'type' => $this->type()?->value,
            'categoryId' => $this->categoryId(),
            'subcategoryId' => $this->subcategoryId(),
            'accountId' => $this->accountId(),
            'minAmount' => $this->minAmount()?->cents,
            'maxAmount' => $this->maxAmount()?->cents,
            'q' => $this->search(),
            'onlyIssues' => $this->onlyIssues(),
        ];
    }

    private function dateFilter(string $key): ?CarbonImmutable
    {
        $value = $this->string($key)->value();

        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function money(string $key): ?Money
    {
        $value = $this->string($key)->value();

        if ($value === '') {
            return null;
        }

        try {
            return Money::fromInput($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function positiveInteger(string $key): ?int
    {
        $value = $this->integer($key);

        return $value > 0 ? $value : null;
    }
}
