<?php

namespace App\Policies;

use App\Models\User;

/**
 * Base policy for panel resources: every ability requires an admin user.
 *
 * Panel access is already gated by User::canAccessPanel(); this adds
 * defense-in-depth at the Gate level so no resource is ever reachable
 * by a non-admin, even if panel access rules change later.
 */
abstract class AdminPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }
}
