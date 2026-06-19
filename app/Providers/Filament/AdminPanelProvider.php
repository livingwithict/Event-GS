<?php

namespace App\Providers\Filament;

use App\Http\Controllers\EventSwitcherController;
use App\Http\Middleware\AdminSessionTimeout;
use App\Http\Middleware\EnsureFilamentAccess;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Event Hub')
            ->favicon(asset('favicon.ico'))
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::Indigo,
                'success' => Color::Emerald,
                'danger' => Color::Red,
                'warning' => Color::Amber,
                'gray' => Color::Slate,
                'info' => Color::Blue,
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Events')
                    ->icon(Heroicon::OutlinedCalendarDays),
                NavigationGroup::make()
                    ->label('Attendees')
                    ->icon(Heroicon::OutlinedUsers),
                NavigationGroup::make()
                    ->label('Finance')
                    ->icon(Heroicon::OutlinedCreditCard),
                NavigationGroup::make()
                    ->label('Communications')
                    ->icon(Heroicon::OutlinedEnvelope),
                NavigationGroup::make()
                    ->label('Settings')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->collapsed(),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->spa()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                AdminSessionTimeout::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureFilamentAccess::class,
            ])
            ->renderHook('panels::sidebar.nav.start', fn () => view('components.event-switcher', [
                'activeEvent' => EventSwitcherController::getActiveEvent(),
            ]));
    }
}
