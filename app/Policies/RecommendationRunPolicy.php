<?php

namespace App\Policies;

use App\Domain\Users\UserRole;
use App\Models\RecommendationRun;
use App\Models\User;

class RecommendationRunPolicy extends AdminManagedPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Owner], true);
    }

    public function retry(User $user, RecommendationRun $run): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Owner], true);
    }
}
