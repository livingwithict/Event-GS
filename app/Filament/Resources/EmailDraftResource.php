<?php

namespace App\Filament\Resources;

use App\Enums\Ability;
use App\Filament\Resources\Concerns\HasRoleBasedVisibility;
use App\Filament\Resources\EmailDraftResource\Pages;
use App\Models\EmailDraft;
use App\Models\Event;
use App\Services\CommunicationService;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

class EmailDraftResource extends Resource
{
    use HasRoleBasedVisibility;

    protected static function requiredAbility(): string
    {
        return Ability::CommunicationsSend;
    }

    protected static ?string $model = EmailDraft::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Communications';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Draft Emails';

    protected static ?string $modelLabel = 'draft email';

    public static function form(Schema $schema): Schema
    {
        $isCustom = fn (Get $get) => $get('type') === EmailDraft::CUSTOM;
        $isHtml = fn (Get $get) => $get('body_format') === EmailDraft::FORMAT_HTML;

        return $schema
            ->columns(1)
            ->components([
                Section::make('Draft')
                    ->columns(2)
                    ->schema([
                        Select::make('event_id')
                            ->label('Event')
                            ->options(fn () => Event::orderByDesc('start_datetime')->pluck('name', 'id'))
                            ->default(fn () => session('active_event_id'))
                            ->required()
                            ->live(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Only you see this — e.g. "Invitation – VIP".'),
                        Select::make('type')
                            ->options(EmailDraft::TYPES)
                            ->default(EmailDraft::CUSTOM)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, Set $set) => $set('attach_ticket', in_array($state, EmailDraft::TICKET_TYPES, true)))
                            ->helperText('What the email is for. Automatic emails (confirmations, reminders…) look for this event\'s default draft of their type.'),
                        Toggle::make('is_default')
                            ->label('Use for automatic emails')
                            ->helperText('Sent automatically for this type. Without one, a plain neutral email is used.')
                            ->visible(fn (Get $get) => ! $isCustom($get)),
                    ]),

                Section::make('Sender')
                    ->columns(2)
                    ->schema([
                        TextInput::make('from_address')
                            ->label('Sender email')
                            ->email()
                            ->maxLength(255)
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                    $domain = Str::lower((string) Event::whereKey($get('event_id'))->value('mail_domain'));
                                    $sender = Str::lower(Str::after((string) $value, '@'));

                                    if ($domain && $sender !== $domain && ! Str::endsWith($sender, '.'.$domain)) {
                                        $fail("The sender must be on this event's mail domain ({$domain}).");
                                    }
                                },
                            ])
                            ->helperText('Leave blank to use the event\'s default sender. Must be on the event\'s mail domain.'),
                        TextInput::make('from_name')
                            ->label('Sender name')
                            ->maxLength(100),
                        TextInput::make('reply_to')
                            ->label('Reply-to')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('subject')
                            ->maxLength(150)
                            ->helperText('Placeholders: '.collect(array_keys(EmailDraft::MERGE_TAGS))->map(fn ($t) => '{{'.$t.'}}')->join(' ')),
                    ]),

                Section::make('Message')
                    ->schema([
                        Select::make('body_format')
                            ->label('Editor')
                            ->options([
                                EmailDraft::FORMAT_RICH => 'Simple editor (headings, bold, links, images)',
                                EmailDraft::FORMAT_HTML => 'Full HTML (complete email with your own layout)',
                            ])
                            ->default(EmailDraft::FORMAT_RICH)
                            ->required()
                            ->live()
                            ->helperText('Switching does not convert the text — it is only a different way to edit it.'),
                        Textarea::make('body')
                            ->visible($isHtml)
                            ->required($isHtml)
                            ->rows(24)
                            ->extraInputAttributes(['style' => 'font-family: ui-monospace, Consolas, monospace; font-size: 12px;', 'spellcheck' => 'false'])
                            ->helperText('Tags: '.collect(EmailDraft::MERGE_TAGS + EmailDraft::HTML_TAGS)->map(fn ($l, $t) => '{{'.$t.'}}')->join(' ')
                                .'. Show a part only when a value exists with {{#if venue}}…{{/if}}, or only when it is missing with {{#unless venue}}…{{/unless}}.'),
                        RichEditor::make('body')
                            ->visible(fn (Get $get) => ! $isHtml($get))
                            ->required(fn (Get $get) => ! $isHtml($get))
                            ->mergeTags(EmailDraft::MERGE_TAGS)
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('email-images')
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'link'],
                                ['h2', 'h3', 'bulletList', 'orderedList', 'blockquote'],
                                ['attachFiles', 'mergeTags', 'undo', 'redo'],
                            ])
                            ->helperText('Insert guest details with the merge-tag button. The event logo header and footer are added automatically.'),
                        Toggle::make('include_qr')
                            ->label('Show the guest\'s entry QR code')
                            ->visible(fn (Get $get) => ! $isHtml($get))
                            ->default(true),
                        Toggle::make('include_face_link')
                            ->label('Add the face-verification button')
                            ->visible(fn (Get $get) => ! $isHtml($get)),
                        Toggle::make('attach_ticket')
                            ->label('Attach the ticket PDF'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => EmailDraft::TYPES[$state] ?? $state),
                TextColumn::make('from_address')->label('Sender')->placeholder('Event default')->searchable(),
                TextColumn::make('subject')->limit(50)->placeholder('—'),
                IconColumn::make('is_default')->label('Automatic')->boolean(),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('No draft emails yet')
            ->emptyStateDescription('Create a draft to set its own sender and subject, or write a new email from scratch.')
            ->modifyQueryUsing(function ($query) {
                if ($eventId = session('active_event_id')) {
                    $query->where('event_id', $eventId);
                }
            })
            ->recordActions([
                Action::make('preview')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (EmailDraft $record) => 'Preview: '.$record->name)
                    ->modalSubmitAction(false)
                    ->modalContent(function (EmailDraft $record) {
                        $registration = $record->event->registrations()->latest('id')->first();

                        if (! $registration) {
                            return new HtmlString('<p>This event has no registrations yet to preview with.</p>');
                        }

                        $html = app(CommunicationService::class)->renderPreview($registration, $record->event, $record->type, $record);

                        return new HtmlString('<p style="margin-bottom:8px;font-size:13px">Shown with the latest guest\'s details: '.e($registration->displayName()).'</p>'
                            .'<iframe srcdoc="'.e($html).'" style="width:100%;height:65vh;border:1px solid #e2e8f0;border-radius:8px"></iframe>');
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailDrafts::route('/'),
            'create' => Pages\CreateEmailDraft::route('/create'),
            'edit' => Pages\EditEmailDraft::route('/{record}/edit'),
        ];
    }
}
