<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Allocation;
use App\Enums\Frequency;
use App\Enums\PlanItemKind;
use App\Enums\PlanItemSource;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\FinancialYear;
use App\Models\SalaryModel;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * Saves the salary arrangement and regenerates the income it implies.
 *
 * The generated plan items are owned by the model: each save rebuilds them, so editing
 * the salary is the single place that changes what the year expects to earn. Manual
 * income items are never touched (INC-06, INC-07).
 */
final readonly class SaveSalaryModel
{
    public function __construct(
        private BuildPlanSchedule $schedule,
        private SyncPlanItemAmounts $amounts,
    ) {}

    /**
     * @param  array<string, mixed>  $payments
     */
    public function handle(FinancialYear $financialYear, Money $baseAmount, array $payments, string $name = 'Salary'): SalaryModel
    {
        return DB::transaction(function () use ($financialYear, $baseAmount, $payments, $name): SalaryModel {
            $salaryModel = $financialYear->salaryModel()->updateOrCreate([], [
                'name' => $name,
                'base_amount_cents' => $baseAmount,
                'payments' => $payments,
            ]);

            $this->regeneratePlanItems($financialYear, $salaryModel);

            return $salaryModel;
        });
    }

    /**
     * Rebuilds from scratch rather than reconciling: the model is the source of truth, so
     * anything it generated previously is replaced wholesale.
     */
    private function regeneratePlanItems(FinancialYear $financialYear, SalaryModel $salaryModel): void
    {
        $salaryModel->planItems()->delete();

        $categories = $this->systemCategories($financialYear);

        $this->createItem(
            $financialYear,
            $salaryModel,
            $categories[Category::KEY_SALARY] ?? null,
            $salaryModel->name,
            $salaryModel->base_amount_cents,
            Frequency::Monthly,
            startMonth: 1,
            sortOrder: 0,
        );

        $sortOrder = 1;

        foreach ($this->bonuses() as $key => $label) {
            $payment = $salaryModel->payments[$key] ?? null;

            if (! is_array($payment) || ($payment['enabled'] ?? false) !== true) {
                continue;
            }

            $this->createItem(
                $financialYear,
                $salaryModel,
                $categories[$key] ?? null,
                $label,
                $this->amountFor($salaryModel->base_amount_cents, $payment),
                Frequency::Once,
                startMonth: $this->monthFor($payment),
                sortOrder: $sortOrder,
            );

            $sortOrder++;
        }
    }

    /**
     * A bonus is either a multiple of the monthly salary or a fixed amount the user
     * typed, because bonuses are often taxed differently (INC-05, INC-08).
     *
     * @param  array<mixed>  $payment
     */
    private function amountFor(Money $baseAmount, array $payment): Money
    {
        if (($payment['mode'] ?? SalaryModel::MODE_MULTIPLIER) === SalaryModel::MODE_FIXED_AMOUNT) {
            $amount = $payment['amount_cents'] ?? 0;

            return Money::fromCents(is_numeric($amount) ? (int) $amount : 0);
        }

        $multiplier = $payment['multiplier'] ?? '1.0';

        if (! is_numeric($multiplier)) {
            return Money::zero();
        }

        // Kept in integer arithmetic: the multiplier is written as a decimal string, so
        // it becomes a ratio over a hundredth rather than a float.
        return $baseAmount->multiplyByRatio((int) round((float) $multiplier * 100), 100);
    }

    /**
     * @param  array<mixed>  $payment
     */
    private function monthFor(array $payment): int
    {
        $month = $payment['month'] ?? 12;

        return is_numeric($month) && $month >= 1 && $month <= 12 ? (int) $month : 12;
    }

    private function createItem(
        FinancialYear $financialYear,
        SalaryModel $salaryModel,
        ?Category $category,
        string $name,
        Money $amount,
        Frequency $frequency,
        int $startMonth,
        int $sortOrder,
    ): void {
        if (! $category instanceof Category) {
            return;
        }

        $planItem = $financialYear->planItems()->create([
            'type' => TransactionType::Income,
            'kind' => PlanItemKind::Recurring,
            'category_id' => $category->id,
            'name' => $name,
            'frequency' => $frequency,
            'start_month' => $startMonth,
            'is_fixed' => true,
            'allocation' => Allocation::LumpSum,
            'source' => PlanItemSource::SalaryModel,
            'salary_model_id' => $salaryModel->id,
            'sort_order' => $sortOrder,
        ]);

        $this->amounts->handle($planItem, $this->schedule->handle($frequency, $amount, $startMonth));
    }

    /**
     * The categories the generated income is filed under, looked up by key so renaming
     * one does not break the link (§12.1).
     *
     * @return array<array-key, Category>
     */
    private function systemCategories(FinancialYear $financialYear): array
    {
        return Category::query()
            ->where('user_id', $financialYear->user_id)
            ->whereIn('system_key', [
                Category::KEY_SALARY,
                Category::KEY_CHRISTMAS_BONUS,
                Category::KEY_EASTER_BONUS,
                Category::KEY_VACATION_ALLOWANCE,
            ])
            ->get()
            ->keyBy('system_key')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function bonuses(): array
    {
        return [
            SalaryModel::CHRISTMAS_BONUS => 'Christmas Bonus',
            SalaryModel::EASTER_BONUS => 'Easter Bonus',
            SalaryModel::VACATION_ALLOWANCE => 'Vacation Allowance',
        ];
    }
}
