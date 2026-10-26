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
                Section::make('Prenotazioni')
                    ->description('Regole applicate a tutte le pratiche.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('option_hours_agency')
                            ->label('Durata opzione agenzie')
                            ->suffix('ore')
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('option_hours_guest')
                            ->label('Durata opzione clienti')
                            ->suffix('ore')
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('quote_validity_days')
                            ->label('Validità preventivo')
                            ->suffix('giorni')
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('price_lock_amount')
                            ->label('Prezzo "Blocca prezzo" (a pratica)')
                            ->prefix('€')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('deposit_percent')
                            ->label('Acconto alla conferma')
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),
                        TextInput::make('default_agency_fee_percent')
                            ->label('Fee predefinita agenzie')
                            ->helperText('Applicata alle agenzie senza fee personalizzata.')
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),
                    ]),
                Section::make("Fasce d'età")
                    ->description('Età compiuta alla data del servizio. Adulti: dall\'età successiva a quella massima dei bambini.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('infant_max_age')
                            ->label('Infant fino a (anni compiuti)')
                            ->helperText('1 = sotto i 2 anni.')
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('child_max_age')
                            ->label('Bambino fino a (anni compiuti)')
                            ->integer()
                            ->gt('infant_max_age')
                            ->required(),
                    ]),
                Section::make('Bonifico (proforma)')
                    ->columns(3)
                    ->schema([
                        TextInput::make('bank_beneficiary')
                            ->label('Beneficiario'),
                        TextInput::make('bank_name')
                            ->label('Banca'),
                        TextInput::make('bank_iban')
                            ->label('IBAN')
                            ->regex('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/')
                            ->validationMessages(['regex' => 'IBAN non valido (senza spazi).']),
                    ]),
                Section::make('WhatsApp')
                    ->schema([
                        TextInput::make('whatsapp_number')
                            ->label('Numero WhatsApp')
                            ->helperText('Formato internazionale senza + né spazi, es. 393331234567.')
                            ->regex('/^\d{8,15}$/'),
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
