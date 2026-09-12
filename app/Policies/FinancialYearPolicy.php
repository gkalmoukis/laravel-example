<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FinancialYear;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A year belonging to someone else is reported as missing rather than forbidden, so its
 * existence is never revealed (USR-02, USR-05).
 */
final readonly class FinancialYearPolicy
{
    public function view(User $user, FinancialYear $financialYear): Response
    {
        return $this->owns($user, $financialYear);
    }

    public function update(User $user, FinancialYear $financialYear): Response
    {
        return $this->owns($user, $financialYear);
    }

    private function owns(User $user, FinancialYear $financialYear): Response
    {
        return $user->id === $financialYear->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
