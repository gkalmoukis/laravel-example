<?php

declare(strict_types=1);

use App\ValueObjects\Money;

it('parses every amount format a user may type', function (string $input, int $expected): void {
    expect(Money::fromInput($input)->cents)->toBe($expected);
})->with([
    'comma decimal' => ['12,50', 1250],
    'dot decimal' => ['12.50', 1250],
    'dot thousands, comma decimal' => ['1.234,56', 123456],
    'plain dot decimal' => ['1234.56', 123456],
    'whole number' => ['1234', 123400],
    'one decimal place' => ['12,5', 1250],
    'zero' => ['0', 0],
    'greek formatting with the currency sign' => ["1.234,56\u{00A0}€", 123456],
    'narrow no-break space' => ["1\u{202F}234,56", 123456],
    'surrounding whitespace' => ['  12,50  ', 1250],
]);

it('reads a lone separator before three digits as thousands', function (string $input, int $expected): void {
    expect(Money::fromInput($input)->cents)->toBe($expected);
})->with([
    'comma' => ['1,234', 123400],
    'dot' => ['12.500', 1250000],
    'repeated separators' => ['1.234.567', 123456700],
    'three digits is never a fraction' => ['12,505', 1250500],
]);

it('rejects anything that is not a well-formed amount', function (string $input): void {
    expect(fn (): Money => Money::fromInput($input))->toThrow(InvalidArgumentException::class);
})->with([
    'empty' => [''],
    'whitespace only' => ['   '],
    'letters' => ['abc'],
    'negative' => ['-12,50'],
    'three decimals with both separators' => ['1.234,567'],
    'four digits after a separator' => ['12,5055'],
    'trailing separator' => ['12,'],
    'above the maximum' => ['10000000,01'],
]);

it('refuses negative amounts', function (): void {
    expect(fn (): Money => Money::fromCents(-1))->toThrow(InvalidArgumentException::class);
});

it('adds and subtracts', function (): void {
    expect(Money::fromCents(1250)->plus(Money::fromCents(750))->cents)->toBe(2000)
        ->and(Money::fromCents(1250)->minus(Money::fromCents(250))->cents)->toBe(1000);
});

it('will not subtract below zero', function (): void {
    expect(fn (): Money => Money::fromCents(100)->minus(Money::fromCents(101)))
        ->toThrow(InvalidArgumentException::class);
});

it('rounds half up when multiplying by a ratio', function (int $cents, int $numerator, int $denominator, int $expected): void {
    expect(Money::fromCents($cents)->multiplyByRatio($numerator, $denominator)->cents)->toBe($expected);
})->with([
    'exact half rounds up' => [5, 1, 2, 3],
    'exact half rounds up again' => [15, 1, 2, 8],
    'below half rounds down' => [10, 1, 3, 3],
    'above half rounds up' => [20, 1, 3, 7],
    'whole multiple' => [1000, 3, 1, 3000],
    'identity' => [1234, 1, 1, 1234],
    'zero numerator' => [1234, 0, 1, 0],
]);

it('refuses an impossible ratio', function (int $numerator, int $denominator): void {
    expect(fn (): Money => Money::fromCents(100)->multiplyByRatio($numerator, $denominator))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'zero denominator' => [1, 0],
    'negative numerator' => [-1, 2],
    'negative denominator' => [1, -2],
]);

it('spreads an annual amount across twelve months exactly, remainder last', function (): void {
    // The worked example from the calculation rules: 1.000,00 € across a year.
    $parts = Money::fromCents(100_000)->allocate(12);

    $cents = array_map(fn (Money $money): int => $money->cents, $parts);

    expect($cents)->toBe([8333, 8333, 8333, 8333, 8333, 8333, 8333, 8333, 8334, 8334, 8334, 8334])
        ->and(array_sum($cents))->toBe(100_000);
});

it('always allocates back to the original amount', function (int $cents, int $parts): void {
    $allocated = Money::fromCents($cents)->allocate($parts);

    expect($allocated)->toHaveCount($parts)
        ->and(array_sum(array_map(fn (Money $money): int => $money->cents, $allocated)))->toBe($cents);
})->with([
    'exact division' => [1200, 12],
    'one cent remainder' => [1201, 12],
    'eleven cents remainder' => [1211, 12],
    'single part' => [999, 1],
    'nothing to allocate' => [0, 12],
    'less than one cent each' => [5, 12],
]);

it('refuses to allocate across no parts', function (): void {
    expect(fn (): array => Money::fromCents(100)->allocate(0))
        ->toThrow(InvalidArgumentException::class);
});

it('compares amounts', function (): void {
    expect(Money::zero()->isZero())->toBeTrue()
        ->and(Money::fromCents(1)->isZero())->toBeFalse()
        ->and(Money::fromCents(1250)->equals(Money::fromCents(1250)))->toBeTrue()
        ->and(Money::fromCents(1250)->equals(Money::fromCents(1251)))->toBeFalse();
});

it('accepts the maximum supported amount but nothing above it', function (): void {
    expect(Money::fromCents(Money::MAX_CENTS)->cents)->toBe(1_000_000_000)
        ->and(fn (): Money => Money::fromCents(Money::MAX_CENTS + 1))
        ->toThrow(InvalidArgumentException::class);
});
