<?php

namespace App\Filament\Resources\Concerns;

use App\Enums\Ability;
use Illuminate\Support\Facades\Auth;

/**
 * A resource declares the ability it needs, not the roles that happen to have
 * it today, so adding or re-scoping a role never means editing every resource.
 */
trait HasRoleBasedVisibility
{
    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasAbility(static::requiredAbility()) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected static function requiredAbility(): string
    {
        return Ability::EventsView;
    }
}
