<?php

namespace App\Filament\Resources\Flights\Schemas;

use App\Enums\Airport;
use App\Filament\Support\HelpAction;
use App\Filament\Support\PriceFields;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FlightForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Volo andata e ritorno')
                    ->description('Le notti del "volo + hotel" sono quelle tra andata e ritorno. Dopo averlo creato, abbinalo ai pacchetti nella sezione "Soggiorno e voli".')
                    ->afterHeader([HelpAction::make('flights', 'Voli e soggiorno')])
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('destination_id')
                            ->label('Destinazione')
                            ->relationship('destination', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('departure_airport')
                            ->label('Aeroporto di partenza')
                            ->options(Airport::class)
                            ->required(),
                        DatePicker::make('outbound_date')
                            ->label('Data andata')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),
                        DatePicker::make('return_date')
                            ->label('Data ritorno')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->after('outbound_date')
                            ->required(),
                        TextInput::make('carrier')
                            ->label('Compagnia')
                            ->maxLength(255),
                        TextInput::make('seats')
                            ->label('Posti acquistati')
                            ->helperText('Gli infant non occupano posto.')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('outbound_details')
                            ->label('Dettagli andata')
                            ->placeholder('Es. NO1234 · 08:30 - 12:45')
                            ->maxLength(255),
                        TextInput::make('return_details')
                            ->label('Dettagli ritorno')
                            ->placeholder('Es. NO1235 · 14:00 - 18:15')
                            ->maxLength(255),
                    ]),
                Section::make('Prezzi a persona (andata e ritorno)')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        PriceFields::money('adult_price', 'Adulto', required: true),
                        PriceFields::money('child_price', 'Bambino')->helperText('Se vuoto si applica il prezzo adulto.'),
                        PriceFields::money('infant_price', 'Infant')->helperText('Se vuoto è gratuito.'),
                        Toggle::make('is_active')
                            ->label('Attivo')
                            ->default(true),
                    ]),
            ]);
    }
}
