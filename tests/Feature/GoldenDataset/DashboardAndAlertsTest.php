<?php

declare(strict_types=1);

use App\Actions\BuildAlerts;
use App\Data\Alert;
use App\Enums\AlertType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\Fixtures\GoldenYear;

/*
 * TST-02, the last part: the figures the home screen puts in front of the user, and the
 * alerts it raises, checked against the same hand-computed year.
 *
 * The dashboard is where every rule in section 7 arrives at once, so this is the test that
 * would notice a change that each rule's own test still lets through.
 */

/**
 * @return array<string, mixed>
 */
function goldenDashboard(User $user): array
{
    $props = null;

    test()->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(function ($page) use (&$props): void {
            $props = $page->toArray()['props'];
        });

    return $props;
}

/**
 * @return list<Alert>
 */
function goldenAlerts(string $today): array
{
    [, $year] = GoldenYear::buildWithEveryAlert();

    return resolve(BuildAlerts::class)->handle($year, CarbonImmutable::parse($today));
}

it('shows what is available now and where the year ends', function (): void {
    [$user] = GoldenYear::build();

    $this->travelTo(GoldenYear::YEAR.'-03-15 09:00:00');

    $props = goldenDashboard($user);

    expect($props['year'])->toBe(GoldenYear::YEAR)
        ->and($props['currentAvailableCents'])->toBe(GoldenYear::CURRENT_AVAILABLE)
        ->and($props['yearEnd']['forecastCents'])->toBe(GoldenYear::FORECAST_YEAR_END)
        ->and($props['yearEnd']['plannedCents'])->toBe(GoldenYear::PLANNED_YEAR_END)
        // No baseline was ever captured, so the drift is measured against the live plan
        // and the card says as much rather than claiming an original it does not have.
        ->and($props['yearEnd']['hasBaseline'])->toBeFalse()
        ->and($props['yearEnd']['deviationCents'])->toBe(GoldenYear::DEVIATION);
});

it('shows income, expenses and savings as actual, plan and forecast', function (): void {
    [$user] = GoldenYear::build();

    $this->travelTo(GoldenYear::YEAR.'-03-15 09:00:00');

    $props = goldenDashboard($user);

    expect($props['income']['actualCents'])->toBe(GoldenYear::ACTUAL_INCOME_SO_FAR)
        ->and($props['income']['plannedCents'])->toBe(GoldenYear::PLAN_INCOME_ANNUAL_TOTAL)
        ->and($props['income']['forecastCents'])->toBe(GoldenYear::FORECAST_INCOME_ANNUAL)
        ->and($props['expenses']['actualCents'])->toBe(GoldenYear::ACTUAL_EXPENSE_SO_FAR)
        ->and($props['expenses']['plannedCents'])->toBe(GoldenYear::PLAN_EXPENSE_ANNUAL)
        ->and($props['expenses']['forecastCents'])->toBe(GoldenYear::FORECAST_EXPENSE_ANNUAL)
        ->and($props['savings']['actualCents'])->toBe(GoldenYear::ACTUAL_SAVINGS_SO_FAR)
        ->and($props['savings']['plannedCents'])->toBe(GoldenYear::PLANNED_SAVINGS_ANNUAL)
        // The rate is divided on the client, so the pieces cross the wire whole.
        ->and($props['savings']['actualIncomeCents'])->toBe(GoldenYear::ACTUAL_INCOME_SO_FAR);
});

it('shows the emergency fund, net worth and what is still outstanding', function (): void {
    [$user] = GoldenYear::build();

    $this->travelTo(GoldenYear::YEAR.'-03-15 09:00:00');

    $props = goldenDashboard($user);

    expect($props['emergencyFund']['currentCents'])->toBe(GoldenYear::MARCH_FUND)
        ->and($props['emergencyFund']['targetCents'])->toBe(GoldenYear::EMERGENCY_TARGET)
        ->and($props['emergencyFund']['isReached'])->toBeFalse()
        ->and($props['emergencyFund']['monthsToTarget'])->toBe(GoldenYear::EMERGENCY_MONTHS_TO_TARGET)
        ->and($props['netWorth']['currentCents'])->toBe(GoldenYear::MARCH_NET_WORTH)
        ->and($props['netWorth']['changeCents'])->toBe(GoldenYear::NET_WORTH_CHANGE)
        ->and($props['completedMonths'])->toBe(GoldenYear::COMPLETED_MONTHS)
        // Nothing is misfiled in the ordinary year.
        ->and($props['transactionIssues'])->toBe(0);
});

it('raises only the overspend in a year that is otherwise in order', function (): void {
    [, $year] = GoldenYear::build();

    $alerts = resolve(BuildAlerts::class)
        ->handle($year, CarbonImmutable::parse(GoldenYear::YEAR.'-03-15'));

    // The holiday was paid in full in March against 300,00 set aside so far, which is the
    // one thing in this year worth saying out loud (ALRT-03).
    expect(array_map(fn (Alert $alert): string => $alert->type->value, $alerts))
        ->toBe([AlertType::BudgetOverrun->value])
        ->and($alerts[0]->detail)->toBe('Holidays');
});

it('raises all seven alerts, worst first, on the year that has gone wrong', function (): void {
    $alerts = goldenAlerts(GoldenYear::TROUBLE_TODAY);

    expect(array_map(fn (Alert $alert): string => $alert->type->value, $alerts))->toBe([
        AlertType::NegativeForecastBalance->value,
        AlertType::ForecastBelowEmergencyFund->value,
        AlertType::TransactionsWithIssues->value,
        AlertType::CategoryTypeMismatch->value,
        AlertType::BudgetOverrun->value,
        AlertType::IncompleteMonth->value,
        AlertType::GoalOffTrack->value,
    ]);
});

it('names what each alert is about', function (): void {
    $alerts = [];

    foreach (goldenAlerts(GoldenYear::TROUBLE_TODAY) as $alert) {
        $alerts[$alert->type->value] = $alert->detail;
    }

    expect($alerts[AlertType::NegativeForecastBalance->value])->toBe(GoldenYear::TROUBLE_NEGATIVE_MONTH)
        ->and($alerts[AlertType::ForecastBelowEmergencyFund->value])->toBe(GoldenYear::TROUBLE_LOWEST_MONTH)
        ->and($alerts[AlertType::TransactionsWithIssues->value])->toBe('1 transaction')
        ->and($alerts[AlertType::CategoryTypeMismatch->value])->toBe('1 transaction')
        ->and($alerts[AlertType::BudgetOverrun->value])->toBe(GoldenYear::TROUBLE_OVERSPENT_CATEGORY)
        ->and($alerts[AlertType::IncompleteMonth->value])->toBe(GoldenYear::TROUBLE_UNFINISHED_MONTH)
        ->and($alerts[AlertType::GoalOffTrack->value])->toBe(GoldenYear::TROUBLE_GOAL);
});

it('gives every alert somewhere to go about it', function (): void {
    foreach (goldenAlerts(GoldenYear::TROUBLE_TODAY) as $alert) {
        $row = $alert->toArray();

        expect($row['title'])->not->toBe('')
            ->and($row['explanation'])->toContain($alert->detail)
            ->and($row['actionLabel'])->not->toBe('')
            ->and($row['actionUrl'])->toStartWith('http');
    }
});

it('counts the misfiled transaction on the dashboard as well', function (): void {
    [$user] = GoldenYear::buildWithEveryAlert();

    $this->travelTo(GoldenYear::TROUBLE_TODAY.' 09:00:00');

    // The same number the alert quotes, because both read it from one place.
    expect(goldenDashboard($user)['transactionIssues'])->toBe(1);
});
