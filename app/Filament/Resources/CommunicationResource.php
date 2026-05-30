<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommunicationResource\Pages;
use App\Models\Communication;
use App\Services\CommunicationService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CommunicationResource extends Resource
{
    protected static ?string $model = Communication::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-envelope';

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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommunications::route('/'),
        ];
    }
}
