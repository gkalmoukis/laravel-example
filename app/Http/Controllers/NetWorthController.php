<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CalculateNetWorth;
use App\Actions\ResolveSelectedYear;
use App\Data\NetWorthHolding;
use App\Data\NetWorthMonth;
use App\Enums\NetWorthItemKind;
use App\Models\FinancialYear;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Everything the user owns and owes (NW-01 … NW-04).
 */
final readonly class NetWorthController
{
    public function index(#[CurrentUser] User $user, CalculateNetWorth $calculator): Response
    {
        $year = $this->selectedYear($user);

        if (! $year instanceof FinancialYear) {
            return Inertia::render('goals/net-worth', [
                'hasYear' => false,
                'year' => null,
                'current' => null,
                'changeVsPreviousMonth' => null,
                'changeVsStartOfYear' => null,
                'months' => [],
                'items' => $this->items($user),
                'kinds' => $this->kinds(),
            ]);
        }

        $position = $calculator->handle($year);

        return Inertia::render('goals/net-worth', [
            'hasYear' => true,
            'year' => $year->year,
            'current' => $this->presentMonth($position->current()),
            'changeVsPreviousMonth' => $position->changeVsPreviousMonth(),
            'changeVsStartOfYear' => $position->changeVsStartOfYear(),
            'months' => $this->presentMonths($position->months),
            'items' => $this->items($user),
            'kinds' => $this->kinds(),
        ]);
    }

    /**
     * @param  array<int, NetWorthMonth>  $months
     * @return list<array<string, mixed>>
     */
    private function presentMonths(array $months): array
    {
        $presented = [];

        foreach ($months as $month) {
            $presented[] = $this->presentMonth($month);
        }

        return $presented;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMonth(NetWorthMonth $month): array
    {
        $holdings = [];

        foreach ($month->holdings as $holding) {
            $holdings[] = $this->presentHolding($holding);
        }

        return [
            'month' => $month->month,
            'assetsCents' => $month->assetsCents,
            'debtsCents' => $month->debtsCents,
            'netCents' => $month->netCents(),
            'byKind' => $month->byKind,
            'holdings' => $holdings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentHolding(NetWorthHolding $holding): array
    {
        return [
            'itemId' => $holding->itemId,
            'name' => $holding->name,
            'kind' => $holding->kind->value,
            'valueCents' => $holding->valueCents,
            // Shown muted with an explanation rather than as a fresh figure (NW-04).
            'isCarriedForward' => $holding->isCarriedForward,
        ];
    }

    /**
     * Holdings the user can manage, retired ones included so they can be brought back.
     *
     * @return list<array<string, mixed>>
     */
    private function items(User $user): array
    {
        $items = [];

        $records = $user->netWorthItems()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($records as $item) {
            $items[] = [
                'id' => $item->id,
                'name' => $item->name,
                'kind' => $item->kind->value,
                'isActive' => $item->is_active,
            ];
        }

        return $items;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function kinds(): array
    {
        return [
            ['value' => NetWorthItemKind::Cash->value, 'label' => 'Cash and bank'],
            ['value' => NetWorthItemKind::EmergencyFund->value, 'label' => 'Emergency fund'],
            ['value' => NetWorthItemKind::Investment->value, 'label' => 'Investment'],
            ['value' => NetWorthItemKind::OtherAsset->value, 'label' => 'Something else you own'],
            ['value' => NetWorthItemKind::Debt->value, 'label' => 'Debt'],
        ];
    }

    private function selectedYear(User $user): ?FinancialYear
    {
        $years = $user->financialYears()->orderByDesc('year')->get();

        $selected = resolve(ResolveSelectedYear::class)->handle($user, $years, null);

        return $years->firstWhere('year', $selected);
    }
}
