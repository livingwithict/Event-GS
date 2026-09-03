<?php

namespace App\Filament\Resources;

use App\Enums\Ability;
use App\Filament\Resources\Concerns\GuestBulkActions;
use App\Filament\Resources\Concerns\HasRoleBasedVisibility;
use App\Filament\Resources\LogisticsResource\Pages;
use App\Models\InvitationCategory;
use App\Models\Registration;
use App\Services\PickAndDropService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class LogisticsResource extends Resource
{
    use HasRoleBasedVisibility;

    protected static function requiredAbility(): string
    {
        return Ability::DeliveryManage;
    }

    protected static ?string $model = Registration::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Logistics';

    protected static ?string $navigationLabel = 'Deliveries';

    protected static ?string $modelLabel = 'Delivery';

    protected static ?string $pluralModelLabel = 'Logistics';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('guest_number')
                    ->label('Guest #')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->placeholder('Not set'),
                Tables\Columns\TextColumn::make('address')
                    ->label('Address')
                    ->placeholder('Not set')
                    ->limit(40)
                    ->tooltip(fn (Registration $record) => $record->address)
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color(fn ($record) => $record->category?->badge_color ?? 'gray')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('invitationCategory.name')
                    ->label('Invitation Category')
                    ->badge()
                    ->color(fn (Registration $record) => match ($record->invitationCategory?->key) {
                        InvitationCategory::PhysicalEmail => 'info',
                        InvitationCategory::FaceVerification => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sectors.name')
                    ->label('Sectors')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('destination_branch')
                    ->label('Delivery Branch')
                    ->placeholder('Not set')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('destination_area')
                    ->label('Delivery Area')
                    ->placeholder('Not set')
                    ->searchable(),
                Tables\Columns\IconColumn::make('order_created')
                    ->label('Order Created')
                    ->boolean()
                    ->state(fn (Registration $record) => filled($record->pickndrop_order_id)),
                Tables\Columns\TextColumn::make('card_status')
                    ->label('Card Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'ready' => 'Ready',
                        'not_ready' => 'Not Ready',
                        'in_progress' => 'In Progress',
                        'not_needed' => 'Not Needed',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'not_needed' => 'gray',
                        'not_ready' => 'danger',
                        'in_progress' => \Filament\Support\Colors\Color::Yellow,
                        'ready' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('pickndrop_tracking_number')
                    ->label('Tracking #')
                    ->placeholder('—')
                    ->copyable()
                    ->url(fn (Registration $record) => $record->pickndrop_tracking_url, shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('pickndrop_status')
                    ->label('Status')
                    ->badge()
                    ->placeholder('Not checked')
                    ->color(fn (?string $state) => match (true) {
                        $state === null => 'gray',
                        str_contains(strtolower($state), 'deliver') => 'success',
                        str_contains(strtolower($state), 'cancel'), str_contains(strtolower($state), 'fail') => 'danger',
                        default => 'warning',
                    })
                    ->description(fn (Registration $record) => $record->pickndrop_status_checked_at
                        ? 'Checked '.$record->pickndrop_status_checked_at->diffForHumans()
                        : null),
            ])
            ->modifyQueryUsing(function ($query) {
                $eventId = session('active_event_id');
                if ($eventId) {
                    $query->where('event_id', $eventId);
                }
            })
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No guests yet')
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->relationship('event', 'name')
                    ->label('Event'),
                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Category'),
                Tables\Filters\SelectFilter::make('invitation_category_id')
                    ->relationship('invitationCategory', 'name')
                    ->label('Invitation Category'),
                Tables\Filters\SelectFilter::make('sectors')
                    ->relationship('sectors', 'name')
                    ->multiple()
                    ->label('Sector'),
                Tables\Filters\SelectFilter::make('card_status')
                    ->options(['ready' => 'Ready', 'not_ready' => 'Not Ready', 'in_progress' => 'In Progress', 'not_needed' => 'Not Needed'])
                    ->label('Card Status'),
                Tables\Filters\SelectFilter::make('destination_branch')
                    ->label('Delivery Branch')
                    ->options(fn () => collect(app(PickAndDropService::class)->getBranches())
                        ->pluck('branch_name', 'name')),
                Tables\Filters\TernaryFilter::make('order_created')
                    ->label('Order Created')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('pickndrop_order_id'),
                        false: fn ($query) => $query->whereNull('pickndrop_order_id'),
                    ),
            ])
            ->recordActions([
                Action::make('edit_guest')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Registration $record) => RegistrationResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    GuestBulkActions::bulkEdit(),
                    BulkAction::make('export')
                        ->label('Export CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records) {
                            $csv = "Name,Guest #,Category,Invitation Category,Sectors,Phone,Address,Delivery Branch,Delivery Area,Order Created,Order ID,Tracking Number,Tracking URL,Status\n";
                            foreach ($records as $registration) {
                                $csv .= sprintf(
                                    "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                                    str_replace('"', '""', $registration->name ?? ''),
                                    str_replace('"', '""', $registration->guest_number ?? ''),
                                    str_replace('"', '""', $registration->category?->name ?? ''),
                                    str_replace('"', '""', $registration->invitationCategory?->name ?? ''),
                                    str_replace('"', '""', $registration->sectors->pluck('name')->implode(', ')),
                                    str_replace('"', '""', $registration->phone ?? ''),
                                    str_replace('"', '""', str_replace(["\r", "\n"], ' ', $registration->address ?? '')),
                                    str_replace('"', '""', $registration->destination_branch ?? 'Not set'),
                                    str_replace('"', '""', $registration->destination_area ?? 'Not set'),
                                    $registration->pickndrop_order_id ? 'Yes' : 'No',
                                    str_replace('"', '""', $registration->pickndrop_order_id ?? ''),
                                    str_replace('"', '""', $registration->pickndrop_tracking_number ?? ''),
                                    str_replace('"', '""', $registration->pickndrop_tracking_url ?? ''),
                                    str_replace('"', '""', $registration->pickndrop_status ?? 'Not checked')
                                );
                            }

                            return response()->streamDownload(function () use ($csv) {
                                echo $csv;
                            }, 'deliveries.csv', ['Content-Type' => 'text/csv']);
                        }),
                    BulkAction::make('set_destination_branch')
                        ->label('Set Delivery Branch & Area')
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            Forms\Components\Select::make('destination_branch')
                                ->label('Branch')
                                ->options(fn () => collect(app(PickAndDropService::class)->getBranches())
                                    ->pluck('branch_name', 'name'))
                                ->searchable()
                                ->live()
                                ->required(),
                            Forms\Components\Select::make('destination_area')
                                ->label('Area')
                                ->options(function (callable $get) {
                                    $branch = collect(app(PickAndDropService::class)->getBranches())
                                        ->firstWhere('name', $get('destination_branch'));

                                    return collect($branch['area'] ?? [])->mapWithKeys(fn ($area) => [$area => $area]);
                                })
                                ->searchable()
                                ->visible(fn (callable $get) => filled($get('destination_branch')))
                                ->helperText('Required by PickAndDrop to create a courier order.')
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each(fn ($r) => $r->update([
                                'destination_branch' => $data['destination_branch'],
                                'destination_area' => $data['destination_area'] ?? null,
                            ]));

                            Notification::make()->success()
                                ->title("Delivery branch set for {$records->count()} guests")
                                ->send();
                        }),
                    BulkAction::make('create_delivery_orders')
                        ->label('Create Delivery Orders')
                        ->icon('heroicon-o-truck')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $service = app(PickAndDropService::class);
                            $created = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                if ($record->pickndrop_order_id) {
                                    continue;
                                }
                                if (! $record->destination_branch || ! $record->destination_area || ! $record->phone) {
                                    $failed++;

                                    continue;
                                }

                                try {
                                    $data = $service->createOrder($record);
                                    $record->update([
                                        'pickndrop_order_id' => $data['orderID'] ?? null,
                                        'pickndrop_tracking_number' => $data['vendor_tracking_number'] ?? null,
                                        'pickndrop_tracking_url' => $data['tracking_url'] ?? null,
                                    ]);
                                    $created++;
                                } catch (\Throwable $e) {
                                    logger()->error('PickAndDrop createOrder failed: '.$e->getMessage(), ['registration_id' => $record->id]);
                                    $failed++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title("Created {$created} delivery orders".($failed ? ", {$failed} failed" : ''))
                                ->send();
                        }),
                    BulkAction::make('print_delivery_labels')
                        ->label('Print Delivery Labels')
                        ->icon('heroicon-o-tag')
                        ->action(fn (Collection $records) => redirect()->route('delivery.labels', [
                            'registrations' => $records->pluck('id')->implode(','),
                        ])),
                    BulkAction::make('request_pickup')
                        ->label('Request Pickup')
                        ->icon('heroicon-o-truck')
                        ->schema([
                            Forms\Components\TextInput::make('vendor_address')
                                ->label('Pickup Address')
                                ->required()
                                ->helperText('Your business address on file with PickAndDrop.'),
                        ])
                        ->action(function (array $data) {
                            try {
                                app(PickAndDropService::class)->createPickupRequest($data['vendor_address']);

                                Notification::make()->success()
                                    ->title('Pickup requested')
                                    ->send();
                            } catch (\Throwable $e) {
                                logger()->error('PickAndDrop pickup request failed: '.$e->getMessage());

                                Notification::make()->danger()
                                    ->title('Pickup request failed')
                                    ->body($e->getMessage())
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLogistics::route('/'),
        ];
    }
}
