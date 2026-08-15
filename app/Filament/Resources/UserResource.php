<?php

namespace App\Filament\Resources;

use App\Enums\Ability;
use App\Enums\Role;
use App\Filament\Resources\Concerns\HasRoleBasedVisibility;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Event;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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

    protected static function requiredAbility(): string
    {
        return Ability::UsersManage;
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
                            ->options(fn () => Role::options())
                            ->disabled(fn (?User $record) => $record?->id === Auth::id())
                            ->helperText(fn (Get $get, ?User $record) => $record?->id === Auth::id()
                                ? 'You cannot change your own role.'
                                : Role::tryFrom((string) $get('role'))?->description())
                            ->native(false)
                            ->live()
                            ->required()
                            ->default(Role::Viewer->value),
                    ]),
                Section::make('Event Assignments')
                    ->description('Every role except Super Admin only sees the events assigned here.')
                    ->visible(fn (Get $get) => Role::tryFrom((string) $get('role'))?->isEventScoped() ?? false)
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
                    ->formatStateUsing(fn (string $state) => Role::tryFrom($state)?->label() ?? $state)
                    ->color(fn (string $state) => Role::tryFrom($state)?->colour() ?? 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('assignedEvents.name')
                    ->label('Events')
                    ->badge()
                    ->limitList(2)
                    ->placeholder('All events')
                    ->toggleable(),
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
        return Auth::user()?->hasAbility(Ability::UsersManage) ?? false;
    }

    public static function canDelete($record): bool
    {
        // Nobody deletes themselves, and the last super admin cannot be removed.
        $user = Auth::user();

        if (! $user?->hasAbility(Ability::UsersManage) || $record->id === $user->id) {
            return false;
        }

        return $record->role !== Role::SuperAdmin->value
            || User::where('role', Role::SuperAdmin->value)->count() > 1;
    }

    /** @return array<string, string> */
    public static function getRoleOptions(): array
    {
        return Role::options();
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
