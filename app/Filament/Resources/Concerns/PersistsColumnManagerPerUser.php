<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Filament's column manager (which columns are shown/hidden, and in what
 * order) persists to the session by default, so it resets whenever the
 * session expires or the guest switches browsers/devices. This saves the
 * same state on the user's own record instead, so a staff member's column
 * choices stick permanently, wherever they log in from.
 */
trait PersistsColumnManagerPerUser
{
    protected function getTableColumnsStorageKey(): string
    {
        return md5(static::class);
    }

    protected function loadTableColumnsFromSession(): array
    {
        $stored = Auth::user()?->column_preferences[$this->getTableColumnsStorageKey()] ?? null;

        return $stored ?? $this->getDefaultTableColumnState();
    }

    protected function persistTableColumns(): void
    {
        if (! $this->getTable()->persistsColumnsInSession()) {
            return;
        }

        $user = Auth::user();

        if (! $user) {
            return;
        }

        $preferences = $user->column_preferences ?? [];
        $preferences[$this->getTableColumnsStorageKey()] = $this->tableColumns;

        $user->forceFill(['column_preferences' => $preferences])->saveQuietly();
    }
}
