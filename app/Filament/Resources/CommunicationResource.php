<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommunicationResource\Pages;
use App\Models\Communication;
use App\Services\CommunicationService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class CommunicationResource extends Resource
{
    protected static ?string $model = Communication::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Read-only - communications are created via API
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('registration.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->colors(['email' => 'info', 'sms' => 'success']),
                Tables\Columns\TextColumn::make('subject')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'pending' => 'warning',
                    'sent' => 'success',
                    'failed' => 'danger',
                ]),
                Tables\Columns\TextColumn::make('sent_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(['email' => 'Email', 'sms' => 'SMS']),
                Tables\Filters\SelectFilter::make('status')->options(['pending' => 'Pending', 'sent' => 'Sent', 'failed' => 'Failed']),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn ($record) => $record->status === 'failed')
                    ->action(function ($record) {
                        $record->update(['status' => 'pending']);

                        $commService = app(CommunicationService::class);
                        $reg = $record->registration;

                        if ($record->type === 'email' && $reg && $reg->email) {
                            $event = $reg->event;
                            $commService->sendEmail($reg, $event, $record->subject ?? 'Invitation');
                        } elseif ($record->type === 'sms' && $reg && $reg->phone) {
                            $commService->sendSms($reg, $record->content ?? '');
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('export')
                        ->label('Export CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records) {
                            $csv = "Guest Name,Email,Phone,Event,Type,Subject,Status,Sent At,Error\n";
                            foreach ($records as $comm) {
                                $csv .= sprintf(
                                    "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                                    str_replace('"', '""', $comm->registration?->name ?? ''),
                                    str_replace('"', '""', $comm->registration?->email ?? ''),
                                    str_replace('"', '""', $comm->registration?->phone ?? ''),
                                    str_replace('"', '""', $comm->registration?->event?->name ?? ''),
                                    $comm->type,
                                    str_replace('"', '""', $comm->subject ?? ''),
                                    $comm->status,
                                    $comm->sent_at?->format('Y-m-d H:i:s') ?? '',
                                    str_replace('"', '""', $comm->metadata['error'] ?? ''),
                                );
                            }

                            return response($csv, 200, [
                                'Content-Type' => 'text/csv',
                                'Content-Disposition' => 'attachment; filename="communications.csv"',
                            ]);
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommunications::route('/'),
        ];
    }
}
