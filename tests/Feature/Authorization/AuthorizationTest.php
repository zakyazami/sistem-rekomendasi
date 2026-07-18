<?php

use App\Domain\Users\UserRole;
use App\Models\Category;
use App\Models\RecommendationRun;
use App\Models\User;
use App\Services\Users\UserManagementService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function userWithRole(UserRole $role, string $email): User
{
    return User::query()->create([
        'name' => $role === UserRole::Admin ? 'Administrator' : 'Pemilik',
        'email' => $email,
        'password' => 'password-lama',
        'role' => $role,
    ]);
}

it('allows both roles into the panel but reserves master mutations for admins', function () {
    $admin = userWithRole(UserRole::Admin, 'admin-auth@example.test');
    $owner = userWithRole(UserRole::Owner, 'owner-auth@example.test');
    $category = Category::query()->create(['name' => 'Otorisasi']);
    $panel = Filament::getPanel('admin');

    expect($admin->canAccessPanel($panel))->toBeTrue()
        ->and($owner->canAccessPanel($panel))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('update', $category))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('view', $category))->toBeTrue()
        ->and(Gate::forUser($owner)->denies('update', $category))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('create', RecommendationRun::class))->toBeTrue();
});

it('keeps the existing password when edit submits an empty password', function () {
    $admin = userWithRole(UserRole::Admin, 'password-admin@example.test');
    $originalHash = $admin->password;

    $updated = app(UserManagementService::class)->update($admin, [
        'name' => 'Nama Baru',
        'email' => $admin->email,
        'role' => UserRole::Admin->value,
        'password' => '',
    ]);

    expect($updated->name)->toBe('Nama Baru')
        ->and($updated->password)->toBe($originalHash);
});

it('hashes and stores a nonempty replacement password', function () {
    $admin = userWithRole(UserRole::Admin, 'replace-admin@example.test');

    $updated = app(UserManagementService::class)->update($admin, [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => UserRole::Admin->value,
        'password' => 'password-baru',
    ]);

    expect(Hash::check('password-baru', $updated->password))->toBeTrue();
});

it('prevents deleting oneself or the last admin', function () {
    $admin = userWithRole(UserRole::Admin, 'last-admin@example.test');
    $owner = userWithRole(UserRole::Owner, 'delete-owner@example.test');
    $service = app(UserManagementService::class);

    expect(fn () => $service->delete($admin, $admin))->toThrow(ValidationException::class);
    expect(fn () => $service->delete($admin, $owner))->toThrow(ValidationException::class);
});

it('allows an admin to delete another nonadmin user', function () {
    $admin = userWithRole(UserRole::Admin, 'delete-admin@example.test');
    $owner = userWithRole(UserRole::Owner, 'other-owner@example.test');

    app(UserManagementService::class)->delete($owner, $admin);

    expect(User::query()->whereKey($owner->id)->doesntExist())->toBeTrue();
});
