<?php

declare(strict_types=1);

use App\Actions\BuildPlanSchedule;
use App\Enums\Allocation;
use App\Enums\Frequency;
use App\ValueObjects\Money;

function schedule(
    Frequency $frequency,
    int $cents = 10_000,
    int $startMonth = 1,
    Allocation $allocation = Allocation::LumpSum,
    array $customMonths = [],
): array {
    $months = resolve(BuildPlanSchedule::class)->handle(
        $frequency,
        Money::fromCents($cents),
        $startMonth,
        $allocation,
        $customMonths,
    );

    return array_map(fn (Money $money): int => $money->cents, $months);
}

it('always produces twelve months, keyed 1 to 12', function (): void {
    $months = schedule(Frequency::Monthly);

    expect($months)->toHaveCount(12)
        ->and(array_keys($months))->toBe(range(1, 12));
});

it('places a one-off and an annual amount in the starting month alone', function (Frequency $frequency): void {
    $months = schedule($frequency, 10_000, 4);

    expect($months[4])->toBe(10_000)
        ->and(array_sum($months))->toBe(10_000);
})->with([Frequency::Once, Frequency::Annual]);

it('repeats a monthly amount from the starting month to the end of the year', function (): void {
    $months = schedule(Frequency::Monthly, 10_000, 4);

    expect(array_slice($months, 0, 3, true))->toBe([1 => 0, 2 => 0, 3 => 0])
        ->and(array_sum($months))->toBe(90_000);
});

it('repeats quarterly every third month', function (): void {
    $months = schedule(Frequency::Quarterly, 10_000, 2);

    expect(array_keys(array_filter($months)))->toBe([2, 5, 8, 11]);
});

it('repeats twice a year, and only once when the second would fall outside it', function (int $startMonth, array $expected): void {
    expect(array_keys(array_filter(schedule(Frequency::SemiAnnual, 10_000, $startMonth))))->toBe($expected);
})->with([
    'both within the year' => [1, [1, 7]],
    'second is December' => [6, [6, 12]],
    'second falls outside' => [8, [8]],
]);

it('uses exactly the months the user ticked', function (): void {
    $months = schedule(Frequency::Custom, 5_000, 1, Allocation::LumpSum, [3, 9, 3, 13, 0]);

    // Duplicates collapse and out-of-range months are dropped.
    expect(array_keys(array_filter($months)))->toBe([3, 9])
        ->and(array_sum($months))->toBe(10_000);
});

it('sets nothing aside when no months are ticked', function (): void {
    expect(array_sum(schedule(Frequency::Custom, 5_000)))->toBe(0);
});

it('spreads an annual total across the year, to the cent', function (): void {
    $months = schedule(Frequency::Annual, 100_000, 7, Allocation::Spread);

    expect(array_values($months))
        ->toBe([8333, 8333, 8333, 8333, 8333, 8333, 8333, 8333, 8334, 8334, 8334, 8334])
        ->and(array_sum($months))->toBe(100_000);
});

it('ignores the frequency when the amount is spread', function (Frequency $frequency): void {
    expect(array_sum(schedule($frequency, 100_000, 5, Allocation::Spread)))->toBe(100_000);
})->with([Frequency::Once, Frequency::Monthly, Frequency::Quarterly, Frequency::Annual]);

it('refuses a starting month outside the year', function (int $startMonth): void {
    expect(fn (): array => schedule(Frequency::Monthly, 10_000, $startMonth))
        ->toThrow(InvalidArgumentException::class);
})->with([0, 13, -1]);

it('plans nothing when the amount is zero', function (): void {
    expect(array_sum(schedule(Frequency::Monthly, 0)))->toBe(0);
});
