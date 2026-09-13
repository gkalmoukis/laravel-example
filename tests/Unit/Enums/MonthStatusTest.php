<?php

declare(strict_types=1);

use App\Enums\MonthStatus;

it('says what each month state contributes to the forecast', function (): void {
    // The badge on every forecast row (FC-02).
    expect(MonthStatus::Complete->forecastSource())->toBe('Actual')
        ->and(MonthStatus::InProgress->forecastSource())->toBe('Plan + actual')
        ->and(MonthStatus::NotStarted->forecastSource())->toBe('Plan');
});
