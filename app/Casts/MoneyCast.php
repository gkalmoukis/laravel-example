<?php

declare(strict_types=1);

namespace App\Casts;

use App\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a `*_cents` column to a Money value object.
 *
 * Inertia props always send `->cents`, so money crosses the wire as an integer and is
 * formatted on the client with the user's locale (FE-18).
 *
 * Nullable columns — a goal with no target, a contribution nobody set — cast to null
 * rather than to zero, because "not decided" and "nothing" are different answers. The
 * generic says so, or PHPStan reads every money attribute as always present.
 *
 * @implements CastsAttributes<Money|null, Money|int|null>
 */
final readonly class MoneyCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        assert(is_numeric($value));

        return Money::fromCents((int) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof Money ? $value->cents : $value;
    }
}
