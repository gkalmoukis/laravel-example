<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * An amount of money, held as integer cents.
 *
 * Direction comes from the owning record's type, never from the sign, so an amount is
 * always zero or positive. Every operation is integer arithmetic: no float ever touches
 * money, which an architecture test enforces (NFR-02).
 */
final readonly class Money
{
    /**
     * Ten million euros, the largest amount any form accepts (TXV-01).
     */
    public const int MAX_CENTS = 1_000_000_000;

    private function __construct(public int $cents)
    {
        throw_if($cents < 0, InvalidArgumentException::class, 'Money cannot be negative; direction comes from the type.');

        throw_if($cents > self::MAX_CENTS, InvalidArgumentException::class, 'Money exceeds the maximum supported amount.');
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Parses the amount formats a user may type (TXQ-03): "12,50", "12.50", "1.234,56"
     * and "1234.56".
     *
     * When both separators appear the rightmost is the decimal separator. When only one
     * appears it is a decimal separator if one or two digits follow, and a thousands
     * separator if exactly three do — three digits can never be a fraction, because
     * amounts are capped at two decimal places.
     */
    public static function fromInput(string $input): self
    {
        // el-GR renders amounts as "1.234,56 €", with a non-breaking space before the sign.
        $value = str_replace(["\u{00A0}", "\u{202F}", ' ', '€'], '', mb_trim($input));

        throw_if($value === '', InvalidArgumentException::class, 'Amount is empty.');

        $lastComma = mb_strrpos($value, ',');
        $lastDot = mb_strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimal = $lastComma > $lastDot ? ',' : '.';
            $thousands = $decimal === ',' ? '.' : ',';
            $value = str_replace($thousands, '', $value);
            $value = str_replace($decimal, '.', $value);
        } elseif ($lastComma !== false || $lastDot !== false) {
            $separator = $lastComma !== false ? ',' : '.';
            $position = $lastComma !== false ? $lastComma : $lastDot;
            $following = mb_strlen($value) - $position - 1;
            $occurrences = mb_substr_count($value, $separator);

            $value = $following === 3 || $occurrences > 1
                ? str_replace($separator, '', $value)
                : str_replace($separator, '.', $value);
        }

        if (in_array(preg_match('/^\d+(\.\d{1,2})?$/', $value), [0, false], true)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid amount.', $input));
        }

        [$units, $fraction] = array_pad(explode('.', $value), 2, '0');

        return new self(((int) $units) * 100 + (int) mb_str_pad($fraction, 2, '0'));
    }

    public function plus(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function minus(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    /**
     * Multiplies by numerator/denominator, rounding half up.
     *
     * Uses `(2cn + d) / 2d` so the rounding is exact integer arithmetic rather than a
     * float comparison against .5.
     */
    public function multiplyByRatio(int $numerator, int $denominator): self
    {
        throw_if($denominator === 0, InvalidArgumentException::class, 'Cannot divide money by zero.');

        throw_if($numerator < 0 || $denominator < 0, InvalidArgumentException::class, 'Money ratios must be positive.');

        return new self(intdiv(2 * $this->cents * $numerator + $denominator, 2 * $denominator));
    }

    /**
     * Splits the amount into equal parts that sum back to exactly this amount (§7.5).
     *
     * The remainder goes to the **last** parts, so spreading an annual cost across twelve
     * months puts the extra cents at the end of the year rather than the start.
     *
     * @return list<self>
     */
    public function allocate(int $parts): array
    {
        throw_if($parts < 1, InvalidArgumentException::class, 'Money must be allocated across at least one part.');

        $base = intdiv($this->cents, $parts);
        $remainder = $this->cents - $base * $parts;
        $firstIncreased = $parts - $remainder;

        $allocated = [];

        for ($part = 0; $part < $parts; $part++) {
            $allocated[] = new self($part >= $firstIncreased ? $base + 1 : $base);
        }

        return $allocated;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }
}
