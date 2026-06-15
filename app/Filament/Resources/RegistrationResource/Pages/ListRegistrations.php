<?php

namespace App\Filament\Resources\RegistrationResource\Pages;

use App\Filament\Resources\RegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRegistrations extends ListRecords
{
    protected static string $resource = RegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
