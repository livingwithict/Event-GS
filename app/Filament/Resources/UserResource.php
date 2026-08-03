<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\HasRoleBasedVisibility;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Event;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class UserResource extends Resource
{
    use HasRoleBasedVisibility;

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Users';

    protected static function getVisibleRoles(): array
    {
        return ['super_admin', 'admin'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context) => $context === 'create')
                            ->maxLength(255)
                            ->hint(fn (string $context) => $context === 'edit' ? 'Leave blank to keep current password' : null),
                        Forms\Components\Select::make('role')
                            ->options(fn () => static::getRoleOptions())
                            ->disabled(fn (?User $record) => $record?->id === Auth::id())
                            ->required()
                            ->default('manager'),
                    ]),
                Section::make('Event Assignments')
                    ->description('Select the events this user can access.')
                    ->visible(fn (?User $record) => $record === null || in_array($record->role, ['manager', 'scanner', 'finance'], true))
                    ->schema([
                        Forms\Components\CheckboxList::make('assignedEvents')
                            ->label('Assigned Events')
                            ->relationship('assignedEvents', 'name')
                            ->options(fn () => Event::orderBy('start_datetime')->pluck('name', 'id'))
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->colors([
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        'manager' => 'success',
                        'scanner' => 'info',
                        'finance' => 'primary',
                        'viewer' => 'gray',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(20)
            ->paginationPageOptions([10, 20, 50])
            ->emptyStateHeading('No users yet')
            ->emptyStateDescription('Create users to manage the platform.')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (User $record) => $record->id !== Auth::id()),
            ]);
    }

    public static function canEdit($record): bool
    {
        $user = Auth::user();

        return $user !== null
            && ($user->isSuperAdmin() || ($user->isAdmin() && $record->role !== 'super_admin'));
    }

    public static function canDelete($record): bool
    {
        $user = Auth::user();

        return $user !== null
            && $record->id !== $user->id
            && $record->role !== 'super_admin'
            && ($user->isSuperAdmin() || ($user->isAdmin() && $record->role !== 'admin'));
    }

    public static function getRoleOptions(): array
    {
        $currentUser = Auth::user();

        if ($currentUser?->isSuperAdmin()) {
            return [
                'admin' => 'Admin',
                'manager' => 'Manager',
                'scanner' => 'Scanner',
                'finance' => 'Finance',
                'viewer' => 'Viewer',
            ];
        }

        return [
            'manager' => 'Manager',
            'scanner' => 'Scanner',
            'viewer' => 'Viewer',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
