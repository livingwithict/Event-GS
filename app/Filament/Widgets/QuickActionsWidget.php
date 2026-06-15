<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\RegistrationResource;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.quick-actions-widget';

    protected static ?string $heading = 'Quick Actions';

    public function getActions(): array
    {
        return [
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
            [
                'label' => 'View All Events',
                'icon' => 'heroicon-o-calendar',
                'url' => EventResource::getUrl('index'),
                'color' => 'gray',
            ],
            [
                'label' => 'View All Registrations',
                'icon' => 'heroicon-o-users',
                'url' => RegistrationResource::getUrl('index'),
                'color' => 'gray',
            ],
        ];
    }
}
