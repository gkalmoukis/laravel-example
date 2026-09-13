<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\AlertType;

/**
 * One thing worth telling the user, with somewhere to go about it (§8.19).
 *
 * Derived on each request and never stored, so an alert cannot outlive its cause.
 */
final readonly class Alert
{
    public function __construct(
        public AlertType $type,
        /** Which month, which category, or how many — whatever the sentence needs. */
        public string $detail,
        public string $actionUrl,
    ) {}

    public function severity(): int
    {
        return $this->type->severity();
    }

    /**
     * @return array<string, string|int>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'title' => $this->type->title(),
            'explanation' => $this->type->explanation($this->detail),
            'actionLabel' => $this->type->actionLabel(),
            'actionUrl' => $this->actionUrl,
            'severity' => $this->severity(),
        ];
    }
}
