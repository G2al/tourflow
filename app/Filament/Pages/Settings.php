<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Impostazioni';

    protected static ?string $title = 'Impostazioni';

    protected static ?int $navigationSort = 100;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Setting::values());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('WhatsApp')
                    ->description('Numero a cui vengono inviate le richieste di prenotazione dal sito.')
                    ->schema([
                        TextInput::make('whatsapp_number')
                            ->label('Numero WhatsApp')
                            ->helperText('Formato internazionale senza + né spazi, es. 393331234567.')
                            ->regex('/^\d{8,15}$/')
                            ->required(),
                    ]),
                Section::make('Dati tour operator')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Ragione sociale')
                            ->required(),
                        TextInput::make('vat_number')
                            ->label('Partita IVA'),
                        TextInput::make('contact_email')
                            ->label('Email')
                            ->email(),
                        TextInput::make('contact_phone')
                            ->label('Telefono'),
                        TextInput::make('address')
                            ->label('Indirizzo')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Salva')
                            ->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        Setting::setMany($this->form->getState());

        Notification::make()
            ->title('Impostazioni salvate')
            ->success()
            ->send();
    }
}
