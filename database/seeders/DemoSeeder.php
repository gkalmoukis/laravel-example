<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\CompleteMonth;
use App\Actions\CreateFinancialYear;
use App\Actions\CreateGoal;
use App\Actions\CreatePlanItem;
use App\Actions\CreateSubscription;
use App\Actions\CreateTransaction;
use App\Actions\ProvisionUserDefaults;
use App\Actions\SaveNetWorthSnapshots;
use App\Actions\SaveSalaryModel;
use App\Actions\UpdateOpeningPosition;
use App\Enums\Allocation;
use App\Enums\EntrySource;
use App\Enums\Frequency;
use App\Enums\GoalType;
use App\Enums\NetWorthItemKind;
use App\Enums\PlanItemKind;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\SalaryModel;
use App\Models\User;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * A year with enough in it to see the whole application working (§12.2).
 *
 * Everything is built through the same Actions the interface uses, so the demo cannot
 * contain a state the application would not have produced — a seeder writing rows
 * directly is how fixtures drift away from the rules they are supposed to illustrate.
 *
 * Nothing here is random. This dataset is also the browser-test fixture, so a figure that
 * changed between runs would make a failing test impossible to read.
 *
 * The year is 2027 and the seeder works "as of" July of that year: January to April are
 * finished, May and June are under way. Those Actions that need to know what day it is are
 * told that date rather than the real one, so the dataset is coherent whenever it is run.
 */
final class DemoSeeder extends Seeder
{
    public const int YEAR = 2027;

    /**
     * The day the demo is written from: half the year recorded, half still ahead.
     */
    private const string AS_OF = '2027-07-01';

    private const int NET_MONTHLY_CENTS = 180_000;

    /**
     * The monthly budget, in cents, for the categories a person cannot simply stop paying
     * plus the ones they watch (BUD-01).
     *
     * @var array<string, int>
     */
    private const array BUDGET = [
        'Housing' => 65_000,
        'Food & Groceries' => 45_000,
        'Utilities' => 14_000,
        'Phone & Internet' => 5_000,
        'Transportation' => 12_000,
        'Personal Care & Health' => 8_000,
        'Dining Out' => 18_000,
        'Subscriptions' => 4_000,
        'Miscellaneous' => 10_000,
    ];

    /**
     * What actually gets spent, month after month: the category, the subcategory it is
     * filed under, how many of them there are, and what each one costs.
     *
     * @var list<array{category: string, subcategory: ?string, description: string, count: int, cents: int}>
     */
    private const array SPENDING = [
        ['category' => 'Housing', 'subcategory' => null, 'description' => 'Rent', 'count' => 1, 'cents' => 65_000],
        ['category' => 'Food & Groceries', 'subcategory' => 'Supermarket', 'description' => 'Supermarket', 'count' => 10, 'cents' => 4_200],
        ['category' => 'Food & Groceries', 'subcategory' => "Farmers' market (Laiki)", 'description' => 'Laiki', 'count' => 4, 'cents' => 2_600],
        ['category' => 'Utilities', 'subcategory' => 'Electricity', 'description' => 'Electricity', 'count' => 1, 'cents' => 9_000],
        ['category' => 'Utilities', 'subcategory' => 'Water', 'description' => 'Water', 'count' => 1, 'cents' => 4_500],
        ['category' => 'Phone & Internet', 'subcategory' => null, 'description' => 'Mobile and internet', 'count' => 1, 'cents' => 5_000],
        ['category' => 'Transportation', 'subcategory' => null, 'description' => 'Fuel', 'count' => 5, 'cents' => 3_000],
        ['category' => 'Transportation', 'subcategory' => null, 'description' => 'Bus pass', 'count' => 1, 'cents' => 3_000],
        ['category' => 'Personal Care & Health', 'subcategory' => null, 'description' => 'Pharmacy', 'count' => 4, 'cents' => 2_400],
        ['category' => 'Dining Out', 'subcategory' => 'Coffee', 'description' => 'Coffee', 'count' => 14, 'cents' => 380],
        ['category' => 'Dining Out', 'subcategory' => 'Restaurant', 'description' => 'Dinner out', 'count' => 5, 'cents' => 3_200],
        ['category' => 'Miscellaneous', 'subcategory' => null, 'description' => 'Household bits', 'count' => 6, 'cents' => 1_800],
        ['category' => 'Transportation', 'subcategory' => null, 'description' => 'Parking', 'count' => 3, 'cents' => 400],
    ];

    /**
     * The subscriptions, which bring their own subcategories and plan items with them
     * (SUB-02, SUB-04).
     *
     * @var list<array{name: string, cents: int, frequency: Frequency, anchor: string}>
     */
    private const array SUBSCRIPTIONS = [
        ['name' => 'Netflix', 'cents' => 1_299, 'frequency' => Frequency::Monthly, 'anchor' => '2027-01-14'],
        ['name' => 'Spotify', 'cents' => 999, 'frequency' => Frequency::Monthly, 'anchor' => '2027-01-06'],
        ['name' => 'Gym', 'cents' => 3_500, 'frequency' => Frequency::Quarterly, 'anchor' => '2027-01-20'],
    ];

    /**
     * The months with transactions in them, and which of those are finished.
     */
    private const int LAST_RECORDED_MONTH = 6;

    private const int LAST_COMPLETE_MONTH = 4;

    public function run(): void
    {
        $user = User::query()->where('email', DatabaseSeeder::ADMIN_EMAIL)->first();

        if (! $user instanceof User) {
            $this->command->error('No local admin to seed a demo for. Run the database seeder first.');

            return;
        }

        resolve(ProvisionUserDefaults::class)->handle($user);

        if ($user->financialYears()->where('year', self::YEAR)->exists()) {
            $this->command->info(sprintf('The %d demo year already exists; leaving it alone.', self::YEAR));

            return;
        }

        $year = resolve(CreateFinancialYear::class)->handle($user, self::YEAR);

        $this->seedOpeningPosition($year);
        $this->seedSalary($year);
        $this->seedBudget($year, $user);
        $this->seedIrregulars($year, $user);
        $this->seedSubscriptions($user);
        $this->seedGoals($user, $year);
        $this->seedTransactions($user);
        $this->seedNetWorth($year);
        $this->completeMonths($year);

        $this->command->info(sprintf('Seeded the %d demo year for %s.', self::YEAR, $user->email));
    }

    /**
     * Where the year starts (OPEN-01). The emergency fund is part of the liquid balance,
     * so the year opens with 4.000,00 available rather than 2.500,00 (§7.2).
     */
    private function seedOpeningPosition(FinancialYear $year): void
    {
        resolve(UpdateOpeningPosition::class)->handle($year, [
            $this->itemId($year, NetWorthItemKind::Cash) => Money::fromCents(250_000),
            $this->itemId($year, NetWorthItemKind::EmergencyFund) => Money::fromCents(150_000),
            $this->itemId($year, NetWorthItemKind::Investment) => Money::fromCents(800_000),
            $this->itemId($year, NetWorthItemKind::OtherAsset) => Money::fromCents(0),
            $this->itemId($year, NetWorthItemKind::Debt) => Money::fromCents(320_000),
        ]);
    }

    /**
     * 1.800,00 € net on fourteen payments: twelve months plus the Christmas bonus and the
     * two half payments (INC-01 … INC-05).
     */
    private function seedSalary(FinancialYear $year): void
    {
        resolve(SaveSalaryModel::class)->handle(
            $year,
            Money::fromCents(self::NET_MONTHLY_CENTS),
            SalaryModel::defaultPayments(),
        );
    }

    private function seedBudget(FinancialYear $year, User $user): void
    {
        foreach (self::BUDGET as $categoryName => $cents) {
            resolve(CreatePlanItem::class)->handle($year, [
                'category_id' => $this->categoryId($user, $categoryName),
                'name' => $categoryName,
                'type' => TransactionType::Expense->value,
                'frequency' => Frequency::Monthly->value,
                'start_month' => 1,
            ], Money::fromCents($cents));
        }
    }

    /**
     * Three costs that do not arrive monthly. The insurance is set aside for a little each
     * month rather than dropped on the month it is paid, which is what Spread means
     * (IRR-02).
     */
    private function seedIrregulars(FinancialYear $year, User $user): void
    {
        resolve(CreatePlanItem::class)->handle($year, [
            'category_id' => $this->categoryId($user, 'Annual Insurance'),
            'name' => 'Car insurance',
            'type' => TransactionType::Expense->value,
            'kind' => PlanItemKind::Irregular->value,
            'allocation' => Allocation::Spread->value,
            'frequency' => Frequency::Annual->value,
            'start_month' => 9,
        ], Money::fromCents(48_000));

        resolve(CreatePlanItem::class)->handle($year, [
            'category_id' => $this->categoryId($user, 'Holidays'),
            'name' => 'Summer holiday',
            'type' => TransactionType::Expense->value,
            'kind' => PlanItemKind::Irregular->value,
            'frequency' => Frequency::Once->value,
            'start_month' => 8,
        ], Money::fromCents(120_000));

        resolve(CreatePlanItem::class)->handle($year, [
            'category_id' => $this->categoryId($user, 'AADE (Taxes)'),
            'name' => 'Income tax',
            'type' => TransactionType::Expense->value,
            'kind' => PlanItemKind::Irregular->value,
            'frequency' => Frequency::SemiAnnual->value,
            'start_month' => 7,
        ], Money::fromCents(90_000));
    }

    private function seedSubscriptions(User $user): void
    {
        foreach (self::SUBSCRIPTIONS as $subscription) {
            resolve(CreateSubscription::class)->handle($user, [
                'name' => $subscription['name'],
                'amount_cents' => Money::fromCents($subscription['cents']),
                'frequency' => $subscription['frequency']->value,
                'billing_anchor_date' => $subscription['anchor'],
                'category_id' => $this->categoryId($user, 'Subscriptions'),
            ], $this->asOf());
        }
    }

    private function seedGoals(User $user, FinancialYear $year): void
    {
        resolve(CreateGoal::class)->handle($user, [
            'type' => GoalType::Purchase->value,
            'name' => 'New laptop',
            'target_amount_cents' => Money::fromCents(150_000),
            'current_amount_cents' => Money::fromCents(60_000),
            'monthly_contribution_cents' => Money::fromCents(15_000),
            'target_date' => '2027-11-30',
        ]);

        resolve(CreateGoal::class)->handle($user, [
            'type' => GoalType::YearEndBalance->value,
            'name' => 'Finish the year above 6.000',
            'target_amount_cents' => Money::fromCents(600_000),
            'financial_year_id' => $year->id,
        ]);
    }

    /**
     * About sixty a month for the first half of the year, the same shape each month so the
     * comparison screens have something to compare.
     *
     * June carries one transaction filed under an income category, which is the flagged
     * state the transaction list and the alerts are there to surface (TXV-05, ALRT-02).
     */
    private function seedTransactions(User $user): void
    {
        for ($month = 1; $month <= self::LAST_RECORDED_MONTH; $month++) {
            $this->seedIncome($user, $month);
            $this->seedSpending($user, $month);
            $this->seedSubscriptionCharges($user, $month);
        }

        // Filed under Overtime, which records money coming in, so it is flagged until
        // someone moves it (ALRT-02).
        $this->record($user, [
            'type' => TransactionType::Expense,
            'category_id' => $this->categoryId($user, 'Overtime'),
            'description' => 'Miscategorised receipt',
        ], 4_500, '2027-06-18');
    }

    private function seedIncome(User $user, int $month): void
    {
        $this->record($user, [
            'type' => TransactionType::Income,
            'category_id' => $this->categoryId($user, 'Salary'),
            'description' => 'Salary',
        ], self::NET_MONTHLY_CENTS, $this->day($month, 25));

        // Easter and the summer allowance land in the months the salary model expects.
        if ($month === 4) {
            $this->record($user, [
                'type' => TransactionType::Income,
                'category_id' => $this->categoryId($user, 'Easter Bonus'),
                'description' => 'Easter bonus',
            ], intdiv(self::NET_MONTHLY_CENTS, 2), $this->day($month, 12));
        }

        if ($month === 6) {
            $this->record($user, [
                'type' => TransactionType::Income,
                'category_id' => $this->categoryId($user, 'Vacation Allowance'),
                'description' => 'Vacation allowance',
            ], intdiv(self::NET_MONTHLY_CENTS, 2), $this->day($month, 20));
        }

        // Some months bring a little extra, so the income comparison is not a flat line.
        if ($month % 2 === 0) {
            $this->record($user, [
                'type' => TransactionType::Income,
                'category_id' => $this->categoryId($user, 'Overtime'),
                'description' => 'Overtime',
            ], 12_000 + $month * 1_000, $this->day($month, 28));
        }
    }

    private function seedSpending(User $user, int $month): void
    {
        foreach (self::SPENDING as $index => $pattern) {
            for ($occurrence = 0; $occurrence < $pattern['count']; $occurrence++) {
                // A repeating but unvarying figure reads as fake, so each one drifts by a
                // few cents in a way that is the same on every run.
                $drift = (($month * 7) + ($index * 13) + ($occurrence * 29)) % 11;

                $this->record($user, [
                    'type' => TransactionType::Expense,
                    'category_id' => $this->categoryId($user, $pattern['category']),
                    'subcategory_id' => $pattern['subcategory'] === null
                        ? null
                        : $this->subcategoryId($user, $pattern['category'], $pattern['subcategory']),
                    'description' => $pattern['description'],
                ], $pattern['cents'] + $drift * 100, $this->day($month, 2 + $occurrence * 2));
            }
        }
    }

    private function seedSubscriptionCharges(User $user, int $month): void
    {
        foreach ($user->subscriptions()->get() as $subscription) {
            if (! in_array($month, $subscription->billingMonthsIn(self::YEAR), true)) {
                continue;
            }

            $this->record($user, [
                'type' => TransactionType::Expense,
                'category_id' => $subscription->category_id,
                'subcategory_id' => $subscription->subcategory_id,
                'description' => $subscription->name,
            ], $subscription->amount_cents->cents, $subscription
                ->nextBillingDateFrom($this->firstOfMonth($month))
                ->toDateString());
        }
    }

    /**
     * What the user was worth at the end of each of the first four months (NW-03).
     */
    private function seedNetWorth(FinancialYear $year): void
    {
        $snapshots = resolve(SaveNetWorthSnapshots::class);

        for ($month = 1; $month <= 4; $month++) {
            $snapshots->handle($year, $month, [
                $this->itemId($year, NetWorthItemKind::Cash) => Money::fromCents(250_000 + $month * 35_000),
                $this->itemId($year, NetWorthItemKind::EmergencyFund) => Money::fromCents(150_000 + $month * 10_000),
                $this->itemId($year, NetWorthItemKind::Investment) => Money::fromCents(800_000 + $month * 20_000),
                $this->itemId($year, NetWorthItemKind::OtherAsset) => Money::fromCents(0),
                $this->itemId($year, NetWorthItemKind::Debt) => Money::fromCents(320_000 - $month * 8_000),
            ]);
        }
    }

    /**
     * January to April are signed off; May and June are still open, which is what leaves
     * them In progress (MON-01, MON-04).
     */
    private function completeMonths(FinancialYear $year): void
    {
        $complete = resolve(CompleteMonth::class);

        for ($month = 1; $month <= self::LAST_COMPLETE_MONTH; $month++) {
            $complete->handle($year, $month, $this->asOf());
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(User $user, array $attributes, int $cents, string $date): void
    {
        resolve(CreateTransaction::class)->handle(
            $user,
            [...$attributes, 'entry_source' => EntrySource::Form],
            Money::fromCents($cents),
            CarbonImmutable::parse($date),
        );
    }

    /**
     * A day that exists in every month, so February needs no special case (EDGE-05).
     */
    private function day(int $month, int $day): string
    {
        $first = $this->firstOfMonth($month);

        return $first->setDay(min($day, $first->daysInMonth))->toDateString();
    }

    /**
     * Parsed rather than constructed from parts, which would be nullable and force a
     * "did that date make sense" branch nothing could ever reach.
     */
    private function firstOfMonth(int $month): CarbonImmutable
    {
        return CarbonImmutable::parse(sprintf('%d-%02d-01', self::YEAR, $month));
    }

    private function asOf(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::AS_OF);
    }

    private function categoryId(User $user, string $name): int
    {
        return $user->categories()->whereNull('parent_id')->where('name', $name)->sole()->id;
    }

    private function subcategoryId(User $user, string $parentName, string $name): int
    {
        return $user->categories()
            ->where('parent_id', $this->categoryId($user, $parentName))
            ->where('name', $name)
            ->sole()
            ->id;
    }

    private function itemId(FinancialYear $year, NetWorthItemKind $kind): int
    {
        return $year->user->netWorthItems()->where('kind', $kind)->sole()->id;
    }
}
