<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\TransactionType;

/**
 * A category's year: what was planned against where it is heading (FC-05).
 */
final readonly class CategoryForecast
{
    public function __construct(
        public int $categoryId,
        public string $categoryName,
        public TransactionType $type,
        public int $plannedCents,
        public int $forecastCents,
    ) {}

    public function differenceCents(): int
    {
        return $this->forecastCents - $this->plannedCents;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'categoryId' => $this->categoryId,
            'categoryName' => $this->categoryName,
            'plannedCents' => $this->plannedCents,
            'forecastCents' => $this->forecastCents,
            'differenceCents' => $this->differenceCents(),
        ];
    }
}
