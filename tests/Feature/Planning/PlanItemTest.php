<?php

declare(strict_types=1);

use App\Actions\CreatePlanItem;
use App\Actions\DeletePlanItem;
use App\Actions\UpdateBudgetCell;
use App\Actions\UpdatePlanItem;
use App\Enums\Allocation;
use App\Enums\Frequency;
use App\Enums\PlanItemKind;
use App\Enums\PlanItemSource;
use App\Models\Category;
use App\Models\PlanItem;
use App\Models\PlanItemAmount;
use App\Models\Subscription;
use App\ValueObjects\Money;

function expenseCategory(string $name = 'Housing'): Category
{
    return Category::query()->where('name', $name)->firstOrFail();
}

function months(PlanItem $item): array
{
    return $item->amounts()->orderBy('month')->get()
        ->mapWithKeys(fn (PlanItemAmount $a): array => [$a->month => $a->amount_cents->cents])
        ->all();
}

it('creates an item with twelve months from its frequency', function (): void {
    $year = planningYear();

    $item = resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => expenseCategory()->id,
        'name' => 'Rent',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(70_000));

    expect(months($item))->toHaveCount(12)
        ->and(array_sum(months($item)))->toBe(840_000)
        ->and($item->source)->toBe(PlanItemSource::Manual);
});

it('spreads an irregular cost across the year when asked', function (): void {
    $year = planningYear();

    $item = resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => expenseCategory('Holidays')->id,
        'name' => 'Summer holiday',
        'kind' => PlanItemKind::Irregular,
        'frequency' => Frequency::Annual,
        'start_month' => 8,
        'allocation' => Allocation::Spread,
    ], Money::fromCents(100_000));

    expect(array_values(months($item)))
        ->toBe([8333, 8333, 8333, 8333, 8333, 8333, 8333, 8333, 8334, 8334, 8334, 8334])
        ->and(array_sum(months($item)))->toBe(100_000);
});

it('pays an irregular cost in one month when it is not spread', function (): void {
    $year = planningYear();

    $item = resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => expenseCategory('Holidays')->id,
        'name' => 'Summer holiday',
        'kind' => PlanItemKind::Irregular,
        'frequency' => Frequency::Annual,
        'start_month' => 8,
        'allocation' => Allocation::LumpSum,
    ], Money::fromCents(100_000));

    expect(months($item)[8])->toBe(100_000)
        ->and(count(array_filter(months($item))))->toBe(1);
});

it('never spreads a recurring item, whatever is asked', function (): void {
    $year = planningYear();

    $item = resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => expenseCategory()->id,
        'name' => 'Rent',
        'kind' => PlanItemKind::Recurring,
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
        'allocation' => Allocation::Spread,
    ], Money::fromCents(70_000));

    expect($item->allocation)->toBe(Allocation::LumpSum);
});

it('keeps hand-edited months when only the name changes', function (): void {
    $year = planningYear();

    $item = resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => expenseCategory()->id,
        'name' => 'Rent',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(70_000));

    resolve(UpdatePlanItem::class)->setMonth($item, 3, Money::fromCents(99_000));
    resolve(UpdatePlanItem::class)->handle($item, ['name' => 'Rent and service charge']);

    expect($item->refresh()->name)->toBe('Rent and service charge')
        ->and(months($item)[3])->toBe(99_000);
});

it('rebuilds the months when a new amount is supplied', function (): void {
    $year = planningYear();

    $item = resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => expenseCategory()->id,
        'name' => 'Rent',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(70_000));

    resolve(UpdatePlanItem::class)->setMonth($item, 3, Money::fromCents(99_000));
    resolve(UpdatePlanItem::class)->handle($item, [], Money::fromCents(80_000));

    expect(months($item)[3])->toBe(80_000)
        ->and(array_sum(months($item)))->toBe(960_000);
});

it('notices when months have been shaped by hand', function (): void {
    $year = planningYear();

    $item = resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => expenseCategory()->id,
        'name' => 'Rent',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(70_000));

    expect(resolve(UpdatePlanItem::class)->hasHandEditedMonths($item, Money::fromCents(70_000)))->toBeFalse();

    resolve(UpdatePlanItem::class)->setMonth($item, 3, Money::fromCents(99_000));

    expect(resolve(UpdatePlanItem::class)->hasHandEditedMonths($item->refresh(), Money::fromCents(70_000)))->toBeTrue();
});

it('deletes an item and its months with it', function (): void {
    $year = planningYear();

    $item = resolve(CreatePlanItem::class)->handle($year, [
        'category_id' => expenseCategory()->id,
        'name' => 'Rent',
        'frequency' => Frequency::Monthly,
        'start_month' => 1,
    ], Money::fromCents(70_000));

    resolve(DeletePlanItem::class)->handle($item);

    expect(PlanItem::query()->whereKey($item->id)->exists())->toBeFalse()
        ->and(PlanItemAmount::query()->where('plan_item_id', $item->id)->exists())->toBeFalse();
});

it('creates an item named after the category on first entry in the grid', function (): void {
    $year = planningYear();
    $category = expenseCategory();

    $item = resolve(UpdateBudgetCell::class)->handle($year, $category, 5, Money::fromCents(45_000));

    expect($item->name)->toBe($category->name)
        ->and($item->source)->toBe(PlanItemSource::Manual)
        ->and(months($item)[5])->toBe(45_000)
        ->and(array_sum(months($item)))->toBe(45_000);
});

it('edits the existing item rather than adding another', function (): void {
    $year = planningYear();
    $category = expenseCategory();

    resolve(UpdateBudgetCell::class)->handle($year, $category, 5, Money::fromCents(45_000));
    resolve(UpdateBudgetCell::class)->handle($year, $category, 6, Money::fromCents(50_000));

    $items = $year->planItems()->where('category_id', $category->id)->get();

    expect($items)->toHaveCount(1)
        ->and(months($items->firstOrFail())[5])->toBe(45_000)
        ->and(months($items->firstOrFail())[6])->toBe(50_000);
});

it('refuses to edit a cell when the category has several items', function (): void {
    $year = planningYear();
    $category = expenseCategory();

    PlanItem::factory()->count(2)->for($year, 'financialYear')->create([
        'category_id' => $category->id,
        'source' => PlanItemSource::Manual,
        'kind' => PlanItemKind::Recurring,
    ]);

    expect(resolve(UpdateBudgetCell::class)->isEditable($year, $category))->toBeFalse()
        ->and(fn (): PlanItem => resolve(UpdateBudgetCell::class)->handle($year, $category, 5, Money::fromCents(1)))
        ->toThrow(RuntimeException::class);
});

it('ignores generated items when deciding whether a cell is editable', function (): void {
    $year = planningYear();
    $category = expenseCategory();

    // A subscription's generated item sits in the category but is not the user's to edit
    // from the grid.
    PlanItem::factory()->for($year, 'financialYear')->create([
        'category_id' => $category->id,
        'source' => PlanItemSource::Subscription,
    ]);

    expect(resolve(UpdateBudgetCell::class)->isEditable($year, $category))->toBeTrue();
});

it('can be linked to the subscription that generated it', function (): void {
    [$user, $year] = userWithYear();

    $subscriptions = $user->categories()->where('name', 'Subscriptions')->firstOrFail();

    $subscription = Subscription::factory()->for($user)->create([
        'category_id' => $subscriptions->id,
    ]);

    $item = PlanItem::factory()->for($year)->create([
        'category_id' => $subscriptions->id,
        'subscription_id' => $subscription->id,
        'source' => PlanItemSource::Subscription,
    ]);

    expect($item->subscription?->id)->toBe($subscription->id);
});
