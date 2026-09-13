<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Actions\CreateFinancialYear;
use App\Actions\CreatePlanItem;
use App\Actions\ProvisionUserDefaults;
use App\Enums\Allocation;
use App\Enums\Frequency;
use App\Enums\GoalType;
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

    /** Cash at the start of the year; the emergency fund is counted separately. */
    public const int OPENING_BALANCE = 250_000;

    /**
     * The money available at the start: cash plus the emergency fund, which is part of
     * the liquid balance so the forecast can tell when it would be eaten into (§7.2).
     */
    public const int OPENING_LIQUID = 400_000;

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
    public const array PLANNED_CLOSING = [1 => 480_000, 2 => 560_000, 3 => 700_000];

    public const int PLANNED_YEAR_END = 1_480_000;

    /**
     * @var array<int, int>
     */
    public const array ACTUAL_CLOSING = [1 => 492_000, 2 => 577_000, 3 => 546_000];

    public const int FORECAST_YEAR_END = 1_386_000;

    /** Everything that has actually happened by 15 March (§7.2). */
    public const int CURRENT_AVAILABLE = 466_000;

    /*
     * The M5 half: what the user owns and owes, and what they are saving towards.
     *
     * Only Housing and Food are marked essential by provisioning, so the emergency fund
     * target is built from those two and not from the holiday.
     */
    public const int ESSENTIAL_MONTHLY = 110_000;

    public const int EMERGENCY_TARGET = 660_000;

    public const int EMERGENCY_CONTRIBUTION = 20_000;

    /** Deliberately short of the target, so the projection is measured, not the cap. */
    public const int EMERGENCY_PROJECTED_YEAR_END = 390_000;

    public const int EMERGENCY_MONTHS_TO_TARGET = 23;

    public const int OPENING_FUND = 150_000;

    public const int OPENING_INVESTMENT = 800_000;

    public const int OPENING_DEBT = 1_150_000;

    /** 250.000 + 150.000 + 800.000 − 1.150.000, non-zero so a sign error cannot hide. */
    public const int OPENING_NET_WORTH = 50_000;

    public const int MARCH_CASH = 396_000;

    public const int MARCH_FUND = 210_000;

    public const int MARCH_INVESTMENT = 845_000;

    public const int MARCH_DEBT = 1_140_000;

    public const int MARCH_NET_WORTH = 311_000;

    public const int NET_WORTH_CHANGE = 261_000;

    public const int PURCHASE_TARGET = 500_000;

    public const int PURCHASE_SAVED = 200_000;

    public const int PURCHASE_CONTRIBUTION = 100_000;

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
        self::holdings($user, $year);
        self::goals($user, $year);

        return [$user, $year];
    }

    public static function category(User $user, string $name): Category
    {
        return $user->categories()->where('name', $name)->whereNull('parent_id')->firstOrFail();
    }

    /**
     * What the user owns and owes, valued at the start of the year and again in March.
     *
     * February is deliberately left out, so a month with nothing recorded has to carry
     * the opening figures forward (§7.8, NW-04).
     */
    private static function holdings(User $user, FinancialYear $year): void
    {
        $values = [
            [NetWorthItemKind::Cash, self::OPENING_BALANCE, self::MARCH_CASH],
            [NetWorthItemKind::EmergencyFund, self::OPENING_FUND, self::MARCH_FUND],
            [NetWorthItemKind::Investment, self::OPENING_INVESTMENT, self::MARCH_INVESTMENT],
            [NetWorthItemKind::Debt, self::OPENING_DEBT, self::MARCH_DEBT],
        ];

        foreach ($values as [$kind, $opening, $march]) {
            $byMonth = [NetWorthSnapshot::OPENING_MONTH => $opening, 3 => $march];

            $item = $user->netWorthItems()->where('kind', $kind)->firstOrFail();

            foreach ($byMonth as $month => $cents) {
                NetWorthSnapshot::query()->updateOrCreate(
                    [
                        'net_worth_item_id' => $item->id,
                        'financial_year_id' => $year->id,
                        'month' => $month,
                    ],
                    ['value_cents' => Money::fromCents($cents)],
                );
            }
        }
    }

    /**
     * One of each kind that behaves differently: a fund whose figure comes from what is
     * set aside, a balance that comes from the forecast, and a purchase the user keeps up
     * to date themselves (§7.7).
     */
    private static function goals(User $user, FinancialYear $year): void
    {
        $user->goals()
            ->where('type', GoalType::EmergencyFund)
            ->firstOrFail()
            ->update(['monthly_contribution_cents' => Money::fromCents(self::EMERGENCY_CONTRIBUTION)]);

        $user->goals()->create([
            'type' => GoalType::YearEndBalance,
            'name' => 'Money left at the end of 2027',
            'financial_year_id' => $year->id,
            'target_amount_cents' => Money::fromCents(1_000_000),
            'current_amount_cents' => Money::zero(),
        ]);

        $user->goals()->create([
            'type' => GoalType::Purchase,
            'name' => 'New kitchen',
            'target_amount_cents' => Money::fromCents(self::PURCHASE_TARGET),
            'current_amount_cents' => Money::fromCents(self::PURCHASE_SAVED),
            'monthly_contribution_cents' => Money::fromCents(self::PURCHASE_CONTRIBUTION),
        ]);
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
