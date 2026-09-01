<?php

namespace App\Filament\Resources\Concerns;

use App\Enums\Ability;
use App\Models\Registration;
use Filament\Actions\BulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The guest-editing bulk action, shared by the Guests table and the Logistics
 * table so a field added to one (e.g. Sectors) never needs adding twice.
 */
final class GuestBulkActions
{
    public static function bulkEdit(): BulkAction
    {
        return BulkAction::make('bulk_edit')
            ->label('Bulk Edit')
            ->icon('heroicon-o-pencil-square')
            ->visible(fn () => Auth::user()?->hasAbility(Ability::GuestsEdit))
            ->schema([
                Forms\Components\Select::make('salutation')
                    ->label('Title')
                    ->options(array_combine(Registration::SALUTATIONS, Registration::SALUTATIONS))
                    ->native(false)
                    ->placeholder('Leave unchanged'),
                Forms\Components\Select::make('category_id')
                    ->label('Guest Category')
                    ->relationship('category', 'name')
                    ->native(false)
                    ->placeholder('Leave unchanged'),
                Forms\Components\Select::make('invitation_category_id')
                    ->label('Invitation Category')
                    ->relationship('invitationCategory', 'name')
                    ->native(false)
                    ->placeholder('Leave unchanged'),
                Forms\Components\Select::make('card_status')
                    ->label('Card Status')
                    ->options(['ready' => 'Ready', 'not_ready' => 'Not Ready', 'in_progress' => 'In Progress', 'not_needed' => 'Not Needed'])
                    ->native(false)
                    ->placeholder('Leave unchanged'),
                Forms\Components\CheckboxList::make('sectors')
                    ->label('Add Sectors')
                    ->relationship('sectors', 'name')
                    ->bulkToggleable()
                    ->columns(2)
                    ->helperText('Selected sectors are added to each guest\'s existing sectors.'),
            ])
            ->action(function (Collection $records, array $data) {
                $changes = array_filter([
                    'salutation' => $data['salutation'] ?? null,
                    'category_id' => $data['category_id'] ?? null,
                    'invitation_category_id' => $data['invitation_category_id'] ?? null,
                    'card_status' => $data['card_status'] ?? null,
                ], fn ($value) => filled($value));

                $sectors = $data['sectors'] ?? [];

                if (empty($changes) && empty($sectors)) {
                    Notification::make()->warning()
                        ->title('Nothing to update')
                        ->body('Pick at least one field to change.')
                        ->send();

                    return;
                }

                $records->each(function ($r) use ($changes, $sectors) {
                    if (! empty($changes)) {
                        $r->update($changes);
                    }
                    if (! empty($sectors)) {
                        $r->sectors()->syncWithoutDetaching($sectors);
                    }
                });

                Notification::make()->success()
                    ->title("Updated {$records->count()} guests")
                    ->send();
            });
    }
}
