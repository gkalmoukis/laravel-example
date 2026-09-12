<?php

declare(strict_types=1);

use App\Actions\CreateFinancialYear;
use App\Actions\ProvisionUserDefaults;
use App\Models\FinancialYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        Str::createRandomStringsNormally();
        Str::createUuidsNormally();
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Sleep::fake();

        $this->freezeTime();
    })
    ->in('Browser', 'Feature', 'Unit');

expect()->extend('toBeOne', fn () => $this->toBe(1));

function something(): void
{
    // ..
}

/*
 * Shared planning fixtures. Defined here rather than in one test file, so any file can be
 * run on its own.
 */

function planningUser(): User
{
    $user = User::factory()->create();

    resolve(ProvisionUserDefaults::class)->handle($user);

    return $user;
}

function planningYear(int $year = 2027): FinancialYear
{
    return resolve(CreateFinancialYear::class)->handle(planningUser(), $year);
}

/**
 * @return array{0: User, 1: FinancialYear}
 */
function userWithYear(int $year = 2027): array
{
    $user = planningUser();

    return [$user, resolve(CreateFinancialYear::class)->handle($user, $year)];
}
