<?php

namespace App\Services\Users;

use App\Domain\Users\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UserManagementService
{
    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['nullable', 'string', 'min:8'],
        ])->validate();

        if ($user->role === UserRole::Admin
            && $validated['role'] !== UserRole::Admin->value
            && User::query()->where('role', UserRole::Admin->value)->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'Admin terakhir tidak dapat diturunkan perannya.',
            ]);
        }

        if (($validated['password'] ?? '') === '') {
            unset($validated['password']);
        }

        $user->fill($validated)->save();

        return $user->refresh();
    }

    public function delete(User $target, User $actor): void
    {
        if ($target->is($actor)) {
            throw ValidationException::withMessages([
                'user' => 'Pengguna tidak dapat menghapus akunnya sendiri.',
            ]);
        }

        if ($target->role === UserRole::Admin
            && User::query()->where('role', UserRole::Admin->value)->count() <= 1) {
            throw ValidationException::withMessages([
                'user' => 'Admin terakhir tidak dapat dihapus.',
            ]);
        }

        $target->delete();
    }
}
