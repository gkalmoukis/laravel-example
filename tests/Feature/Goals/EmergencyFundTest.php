<?php

declare(strict_types=1);

use App\Actions\CreatePlanItem;
use App\Enums\Frequency;
use App\Enums\GoalType;
use App\Enums\NetWorthItemKind;
use App\Models\FinancialYear;
use App\Models\Goal;
use App\Models\NetWorthSnapshot;
use App\Models\User;
use App\ValueObjects\Money;

function efPlan(FinancialYear $year, User $user, string $categoryName, int $cents): void
{
    resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => $user->categories()->where('name', $categoryName)->whereNull('parent_id')->firstOrFail()->id,
        'name' => $categoryName,
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents($cents));
}

function efGoal(User $user): Goal
{
    return $user->goals()->where('type', GoalType::EmergencyFund)->firstOrFail();
}

/**
 * @return array<string, mixed>
 */
function efPayload(User $user, array $overrides = []): array
{
    return [
        'months_of_cover' => 6,
        'essential_category_ids' => $user->categories()
            ->where('is_essential', true)
            ->whereNull('parent_id')
            ->pluck('id')
            ->all(),
        'custom_target' => '',
        'monthly_contribution' => '',
        ...$overrides,
    ];
}

it('shows where the fund stands', function (): void {
    [$user, $year] = userWithYear();

    efPlan($year, $user, 'Housing', 100_000);

    $fund = $user->netWorthItems()->where('kind', NetWorthItemKind::EmergencyFund)->firstOrFail();

    NetWorthSnapshot::query()
        ->where('net_worth_item_id', $fund->id)
        ->where('financial_year_id', $year->id)
        ->where('month', NetWorthSnapshot::OPENING_MONTH)
        ->update(['value_cents' => 200_000]);

    $this->actingAs($user)
        ->get(route('emergency-fund.show'))
        ->assertInertia(fn ($page) => $page
            ->component('goals/emergency-fund')
            ->where('hasYear', true)
            ->where('status.essentialMonthlyCents', 100_000)
            ->where('status.targetCents', 600_000)
            ->where('status.currentCents', 200_000)
            ->where('status.remainingCents', 400_000)
            ->where('status.hasBeenStarted', true));
});

it('asks for a plan before it can suggest a target', function (): void {
    $user = planningUser();

    // Nothing planned means nothing to multiply, so the screen asks rather than
    // showing a target of nothing.
    $this->actingAs($user)
        ->get(route('emergency-fund.show'))
        ->assertInertia(fn ($page) => $page
            ->where('hasYear', false)
            ->where('status', null));
});

it('says when nothing has been set aside yet', function (): void {
    [$user, $year] = userWithYear();

    efPlan($year, $user, 'Housing', 100_000);

    $this->actingAs($user)
        ->get(route('emergency-fund.show'))
        ->assertInertia(fn ($page) => $page->where('status.hasBeenStarted', false));
});

it('offers the expense categories that could be essential', function (): void {
    [$user, $year] = userWithYear();

    efPlan($year, $user, 'Housing', 100_000);

    $this->actingAs($user)
        ->get(route('emergency-fund.show'))
        ->assertInertia(function ($page): void {
            $categories = collect($page->toArray()['props']['categories']);

            expect($categories->pluck('name'))->toContain('Housing')
                ->and($categories->pluck('name'))->toContain('Dining Out')
                // Income categories are not a question about what must be paid.
                ->and($categories->pluck('name'))->not->toContain('Salary')
                ->and($categories->firstWhere('name', 'Housing')['isEssential'])->toBeTrue()
                ->and($categories->firstWhere('name', 'Dining Out')['isEssential'])->toBeFalse();
        });
});

it('changes how many months of cover the user wants', function (): void {
    [$user, $year] = userWithYear();

    efPlan($year, $user, 'Housing', 100_000);

    $this->actingAs($user)
        ->patch(route('emergency-fund.update'), efPayload($user, ['months_of_cover' => 3]))
        ->assertSessionHasNoErrors();

    expect($user->preference()->firstOrFail()->emergency_fund_months)->toBe(3);
});

it('refuses a number of months that is not a fund', function (int $months): void {
    [$user, $year] = userWithYear();

    efPlan($year, $user, 'Housing', 100_000);

    $this->actingAs($user)
        ->patch(route('emergency-fund.update'), efPayload($user, ['months_of_cover' => $months]))
        ->assertSessionHasErrors('months_of_cover');
})->with([0, 25, -1]);

it('changes which costs would still have to be paid', function (): void {
    [$user, $year] = userWithYear();

    efPlan($year, $user, 'Housing', 100_000);
    efPlan($year, $user, 'Dining Out', 50_000);

    $diningOut = $user->categories()->where('name', 'Dining Out')->whereNull('parent_id')->firstOrFail();

    $this->actingAs($user)
        ->patch(route('emergency-fund.update'), efPayload($user, [
            'essential_category_ids' => [$diningOut->id],
        ]))
        ->assertSessionHasNoErrors();

    // Exactly what was ticked, and nothing else.
    expect($diningOut->refresh()->is_essential)->toBeTrue()
        ->and($user->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail()->is_essential)
        ->toBeFalse();
});

it('recomputes the target from the new choice of essentials', function (): void {
    [$user, $year] = userWithYear();

    efPlan($year, $user, 'Housing', 100_000);
    efPlan($year, $user, 'Dining Out', 50_000);

    $diningOut = $user->categories()->where('name', 'Dining Out')->whereNull('parent_id')->firstOrFail();

    $this->actingAs($user)->patch(route('emergency-fund.update'), efPayload($user, [
        'essential_category_ids' => [$diningOut->id],
    ]));

    // 500,00 a month of essentials now, six months of cover.
    $this->actingAs($user->fresh() ?? $user)
        ->get(route('emergency-fund.show'))
        ->assertInertia(fn ($page) => $page->where('status.targetCents', 300_000));
});

it('unsets every essential when none is ticked', function (): void {
    [$user, $year] = userWithYear();

    efPlan($year, $user, 'Housing', 100_000);

    $this->actingAs($user)
        ->patch(route('emergency-fund.update'), efPayload($user, ['essential_category_ids' => []]))
        ->assertSessionHasNoErrors();

    expect($user->categories()->where('is_essential', true)->count())->toBe(0);
});

it('lets the user name their own target', function (): void {
    [$user, $year] = userWithYear();

    efPlan($year, $user, 'Housing', 100_000);

    $this->actingAs($user)
        ->patch(route('emergency-fund.update'), efPayload($user, ['custom_target' => '10.000,00']))
        ->assertSessionHasNoErrors();

    $goal = efGoal($user);

    expect($goal->target_is_custom)->toBeTrue()
        ->and($goal->target_amount_cents?->cents)->toBe(1_000_000);
});

it('returns to the computed target when the custom one is cleared', function (): void {
    [$user, $year] = userWithYear();

    efPlan($year, $user, 'Housing', 100_000);

    $this->actingAs($user)->patch(route('emergency-fund.update'), efPayload($user, [
        'custom_target' => '10.000,00',
    ]));

    $this->actingAs($user)->patch(route('emergency-fund.update'), efPayload($user, [
        'custom_target' => '',
    ]));

    $goal = efGoal($user);

    expect($goal->target_is_custom)->toBeFalse()
        ->and($goal->target_amount_cents)->toBeNull();
});

it('records what the user puts aside each month', function (): void {
    [$user, $year] = userWithYear();

    efPlan($year, $user, 'Housing', 100_000);

    $this->actingAs($user)
        ->patch(route('emergency-fund.update'), efPayload($user, [
            'monthly_contribution' => '250,00',
        ]))
        ->assertSessionHasNoErrors();

    expect(efGoal($user)->monthly_contribution_cents?->cents)->toBe(25_000);
});

it('refuses an amount that is not a number', function (string $field): void {
    [$user, $year] = userWithYear();

    efPlan($year, $user, 'Housing', 100_000);

    $this->actingAs($user)
        ->patch(route('emergency-fund.update'), efPayload($user, [$field => 'quite a lot']))
        ->assertSessionHasErrors([$field => 'Enter an amount like 1.234,56.']);
})->with(['custom_target', 'monthly_contribution']);

it('refuses another user category as an essential', function (): void {
    [$user, $year] = userWithYear();
    [$other] = userWithYear(2026);

    efPlan($year, $user, 'Housing', 100_000);

    $theirs = $other->categories()->where('name', 'Housing')->whereNull('parent_id')->firstOrFail();

    $this->actingAs($user)
        ->patch(route('emergency-fund.update'), efPayload($user, [
            'essential_category_ids' => [$theirs->id],
        ]))
        ->assertSessionHasErrors('essential_category_ids.0');

    expect($theirs->refresh()->is_essential)->toBeTrue();
});

it('reports when the target will be reached', function (): void {
    [$user, $year] = userWithYear();

    efPlan($year, $user, 'Housing', 100_000);

    $this->actingAs($user)->patch(route('emergency-fund.update'), efPayload($user, [
        'monthly_contribution' => '1.000,00',
    ]));

    // 6.000,00 to find at 1.000,00 a month is six months.
    $this->actingAs($user->fresh() ?? $user)
        ->get(route('emergency-fund.show'))
        ->assertInertia(fn ($page) => $page
            ->where('status.monthsToTarget', 6)
            ->where('status.monthlyContributionCents', 100_000));
});

it('keeps the settings the form needs to show', function (): void {
    [$user, $year] = userWithYear();

    efPlan($year, $user, 'Housing', 100_000);

    $this->actingAs($user)->patch(route('emergency-fund.update'), efPayload($user, [
        'custom_target' => '5.000,00',
        'monthly_contribution' => '100,00',
    ]));

    $this->actingAs($user->fresh() ?? $user)
        ->get(route('emergency-fund.show'))
        ->assertInertia(fn ($page) => $page
            ->where('goal.customTargetCents', 500_000)
            ->where('goal.monthlyContributionCents', 10_000));
});

it('needs a signed-in user', function (): void {
    $this->get(route('emergency-fund.show'))->assertRedirect(route('login'));
});
