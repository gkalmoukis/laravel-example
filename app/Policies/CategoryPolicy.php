<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * See AccountPolicy: another user's record is reported as missing, never as forbidden
 * (USR-02, USR-05).
 */
final readonly class CategoryPolicy
{
    public function view(User $user, Category $category): Response
    {
        return $this->owns($user, $category);
    }

    public function update(User $user, Category $category): Response
    {
        return $this->owns($user, $category);
    }

    /**
     * Categories are never destroyed, only deactivated, so this guards deactivation. A
     * system category must survive, because the salary model and subscriptions reference
     * it by key (CAT-04, CAT-07).
     */
    public function delete(User $user, Category $category): Response
    {
        if ($user->id !== $category->user_id) {
            return Response::denyAsNotFound();
        }

        if ($category->isSystem()) {
            return Response::deny('Built-in categories cannot be removed. You can rename them instead.');
        }

        // A category still collecting subscription charges has to stay somewhere they can
        // be filed (CAT-07).
        if ($category->hasActiveSubscriptions()) {
            return Response::deny('Stop the subscriptions using this category first.');
        }

        return Response::allow();
    }

    private function owns(User $user, Category $category): Response
    {
        return $user->id === $category->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
