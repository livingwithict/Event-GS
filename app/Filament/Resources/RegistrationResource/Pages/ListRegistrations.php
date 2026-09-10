<?php

namespace App\Filament\Resources\RegistrationResource\Pages;

use App\Enums\Ability;
use App\Filament\Resources\Concerns\PersistsColumnManagerPerUser;
use App\Filament\Resources\RegistrationResource;
use App\Models\Event;
use App\Services\GoogleSheetsService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListRegistrations extends ListRecords
{
    use PersistsColumnManagerPerUser;

    protected static string $resource = RegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('update_spreadsheet')
                ->label('Update Spreadsheet')
                ->visible(fn () => Auth::user()?->hasAbility(Ability::GuestsEdit))
                ->action(function () {
                    $event = Event::find(session('active_event_id'));

                    if (! $event) {
                        Notification::make()
                            ->warning()
                            ->title('No active event selected')
                            ->send();

                        return;
                    }

                    try {
                        $count = app(GoogleSheetsService::class)->syncEvent($event);
                    } catch (\RuntimeException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Spreadsheet sync failed')
                            ->body($e->getMessage())
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title($count > 0 ? "Appended {$count} new registrant(s)" : 'Nothing new to sync')
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('approval_status', 'approved')),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('approval_status', 'pending')),
            'waitlisted' => Tab::make('Waitlisted')
                ->badge(fn () => $this->getResource()::getModel()::where('approval_status', 'waitlisted')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('approval_status', 'waitlisted')),
            'rejected' => Tab::make('Rejected')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('approval_status', 'rejected')),
        ];
    }
}
