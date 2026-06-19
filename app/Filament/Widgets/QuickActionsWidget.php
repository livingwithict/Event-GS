<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\RegistrationResource;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Route;

class QuickActionsWidget extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.quick-actions-widget';

    protected static ?string $heading = 'Quick Actions';

    public function getActions(): array
    {
        $eventId = session('active_event_id');
        $onsiteUrl = $eventId ? route('onsite.register', $eventId) : null;
        $importUrl = $eventId ? (Route::has('filament.admin.pages.import-preview') ? route('filament.admin.pages.import-preview') : null) : null;

        $actions = [
            [
                'label' => 'New Event',
                'icon' => 'heroicon-o-plus-circle',
                'url' => EventResource::getUrl('create'),
                'color' => 'primary',
            ],
            [
                'label' => 'New Registration',
                'icon' => 'heroicon-o-user-plus',
                'url' => RegistrationResource::getUrl('create'),
                'color' => 'success',
            ],
        ];

        if ($onsiteUrl) {
            $actions[] = [
                'label' => 'Onsite Registration',
                'icon' => 'heroicon-o-clipboard-document-check',
                'url' => $onsiteUrl,
                'color' => 'success',
            ];
        }

        if ($importUrl) {
            $actions[] = [
                'label' => 'Import Guests',
                'icon' => 'heroicon-o-arrow-up-tray',
                'url' => $importUrl,
                'color' => 'gray',
            ];
        }

        $actions[] = [
            'label' => 'View All Events',
            'icon' => 'heroicon-o-calendar',
            'url' => EventResource::getUrl('index'),
            'color' => 'gray',
        ];

        $actions[] = [
            'label' => 'View All Registrations',
            'icon' => 'heroicon-o-users',
            'url' => RegistrationResource::getUrl('index'),
            'color' => 'gray',
        ];

        return $actions;
    }
}
