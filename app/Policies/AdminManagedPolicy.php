<?php

namespace App\Policies;

use App\Domain\Users\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class AdminManagedPolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === UserRole::Admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Owner;
    }

    public function view(User $user, Model $model): bool
    {
        return $user->role === UserRole::Owner;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $model): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
