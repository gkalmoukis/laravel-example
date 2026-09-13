<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Actions\CreateFinancialYear;
use App\Actions\CreatePlanItem;
use App\Actions\ProvisionUserDefaults;
use App\Enums\Allocation;
use App\Enums\Frequency;
use App\Enums\NetWorthItemKind;
use App\Enums\PlanItemKind;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\MonthClosure;
use App\Models\NetWorthSnapshot;
use App\Models\Transaction;
use App\Models\User;
use App\ValueObjects\Money;

/**
 * One fully specified year, with every figure it implies worked out by hand (TST-02).
 *
 * The other tests each check one rule in isolation. This checks that the rules still
 * agree when they all apply at once — which is where a plausible-looking change tends to
 * go wrong, because each rule still passes its own test.
 *
 * The numbers below are deliberately not round: a year where everything divides evenly
 * hides exactly the errors worth catching.
 *
 * THE SHAPE OF THE YEAR (2027)
 *
 * Income   Salary 2.000,00 every month; Freelance 600,00 in March and September only.
 * Expenses Housing 700,00 and Food 400,00 every month; a 1.200,00 holiday set aside
 *          monthly, so 100,00 a month, actually paid in March.
 * Opening  2.500,00 in cash.
 *
 * Recorded January and February are complete; March is in progress. Food runs over in
 *          February and March; the holiday is paid in full in March.
 */
final readonly class GoldenYear
{
    public const int YEAR = 2027;

    public const int OPENING_BALANCE = 250_000;

    public const int SALARY_PLAN = 200_000;

    public const int FREELANCE_PLAN = 60_000;

    public const int HOUSING_PLAN = 70_000;

    public const int FOOD_PLAN = 40_000;

    public const int HOLIDAY_ANNUAL = 120_000;

    /** 1.200,00 spread across twelve months divides exactly. */
    public const int HOLIDAY_MONTHLY = 10_000;

    /**
     * @var array<int, int>
     */
    public const array ACTUAL_INCOME = [1 => 200_000, 2 => 200_000, 3 => 200_000];

    /**
     * @var array<int, int>
     */
    public const array ACTUAL_HOUSING = [1 => 70_000, 2 => 70_000, 3 => 70_000];

    /**
     * @var array<int, int>
     */
    public const array ACTUAL_FOOD = [1 => 38_000, 2 => 45_000, 3 => 41_000];

    /** The whole holiday, paid in March rather than spread. */
    public const int ACTUAL_HOLIDAY_MONTH = 3;

    public const int PLAN_EXPENSE_MONTHLY = 120_000;

    public const int PLAN_EXPENSE_ANNUAL = 1_440_000;

    public const int PLAN_INCOME_ANNUAL_TOTAL = 2_520_000;

    /**
     * Forecast: complete months keep what happened, later ones keep the larger of plan
     * and actual, per category.
     */
    public const int FORECAST_INCOME_ANNUAL = 2_520_000;

    public const int FORECAST_EXPENSE_ANNUAL = 1_534_000;

    /**
     * @var array<int, int>
     */
    public const array PLANNED_CLOSING = [1 => 330_000, 2 => 410_000, 3 => 550_000];

    public const int PLANNED_YEAR_END = 1_330_000;

    /**
     * @var array<int, int>
     */
    public const array ACTUAL_CLOSING = [1 => 342_000, 2 => 427_000, 3 => 396_000];

    public const int FORECAST_YEAR_END = 1_236_000;

    /**
     * Builds the year. Returns the user and the financial year, both fully populated.
     *
     * @return array{0: User, 1: FinancialYear}
     */
    public static function build(): array
    {
        $user = User::factory()->create();

        resolve(ProvisionUserDefaults::class)->handle($user);

        $year = resolve(CreateFinancialYear::class)->handle($user, self::YEAR);

        self::openingPosition($user, $year);
        self::plan($user, $year);
        self::transactions($user);
        self::completeMonths($year);

        return [$user, $year];
    }

    public static function category(User $user, string $name): Category
    {
        return $user->categories()->where('name', $name)->whereNull('parent_id')->firstOrFail();
    }

    private static function openingPosition(User $user, FinancialYear $year): void
    {
        $cash = $user->netWorthItems()->where('kind', NetWorthItemKind::Cash)->firstOrFail();

        NetWorthSnapshot::query()
            ->where('net_worth_item_id', $cash->id)
            ->where('financial_year_id', $year->id)
            ->where('month', NetWorthSnapshot::OPENING_MONTH)
            ->update(['value_cents' => self::OPENING_BALANCE]);
    }

    private static function plan(User $user, FinancialYear $year): void
    {
        resolve(CreatePlanItem::class)->handle($year, [
            'category_id' => self::category($user, 'Salary')->id,
            'name' => 'Salary',
            'type' => TransactionType::Income->value,
            'frequency' => Frequency::Monthly,
            'start_month' => 1,
        ], Money::fromCents(self::SALARY_PLAN));

        // Twice a year rather than monthly, so a category with gaps is covered.
        foreach ([3, 9] as $month) {
            resolve(CreatePlanItem::class)->handle($year, [
                'category_id' => self::category($user, 'Freelance & Projects')->id,
                'name' => 'Project '.$month,
                'type' => TransactionType::Income->value,
                'frequency' => Frequency::Once,
                'start_month' => $month,
            ], Money::fromCents(self::FREELANCE_PLAN));
        }

        resolve(CreatePlanItem::class)->handle($year, [
            'category_id' => self::category($user, 'Housing')->id,
            'name' => 'Rent',
            'frequency' => Frequency::Monthly,
            'start_month' => 1,
        ], Money::fromCents(self::HOUSING_PLAN));

        resolve(CreatePlanItem::class)->handle($year, [
            'category_id' => self::category($user, 'Food & Groceries')->id,
            'name' => 'Food',
            'frequency' => Frequency::Monthly,
            'start_month' => 1,
        ], Money::fromCents(self::FOOD_PLAN));

        // Set aside monthly, paid in one month — the case month-by-month variance would
        // read wrongly (CMP-05).
        resolve(CreatePlanItem::class)->handle($year, [
            'category_id' => self::category($user, 'Holidays')->id,
            'name' => 'Summer holiday',
            'kind' => PlanItemKind::Irregular,
            'frequency' => Frequency::Annual,
            'start_month' => 7,
            'allocation' => Allocation::Spread,
        ], Money::fromCents(self::HOLIDAY_ANNUAL));
    }

    private static function transactions(User $user): void
    {
        foreach (self::ACTUAL_INCOME as $month => $cents) {
            self::record($user, 'Salary', $month, 25, $cents);
        }

        foreach (self::ACTUAL_HOUSING as $month => $cents) {
            self::record($user, 'Housing', $month, 1, $cents);
        }

        foreach (self::ACTUAL_FOOD as $month => $cents) {
            self::record($user, 'Food & Groceries', $month, 12, $cents);
        }

        self::record($user, 'Holidays', self::ACTUAL_HOLIDAY_MONTH, 20, self::HOLIDAY_ANNUAL);
    }

    private static function record(User $user, string $categoryName, int $month, int $day, int $cents): void
    {
        $category = self::category($user, $categoryName);

        Transaction::factory()->for($user)->create([
            'type' => $category->type,
            'category_id' => $category->id,
            'occurred_on' => sprintf('%d-%02d-%02d', self::YEAR, $month, $day),
            'amount_cents' => $cents,
            'description' => $categoryName.' '.$month,
        ]);
    }

    private static function completeMonths(FinancialYear $year): void
    {
        foreach ([1, 2] as $month) {
            MonthClosure::factory()->for($year)->create([
                'month' => $month,
                'completed_at' => now(),
            ]);
        }
    }
}
