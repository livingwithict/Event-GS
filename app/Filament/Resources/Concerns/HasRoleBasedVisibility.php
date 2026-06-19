<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Support\Facades\Auth;

trait HasRoleBasedVisibility
{
    private static function userHasRole(string ...$roles): bool
    {
        return in_array(Auth::user()?->role, $roles);
    }

    public static function canAccess(array $parameters = []): bool
    {
        return static::userHasRole(...static::getVisibleRoles());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::userHasRole(...static::getVisibleRoles());
    }

    protected static function getVisibleRoles(): array
    {
        return ['super_admin', 'admin'];
    }
}
