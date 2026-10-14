<?php

namespace App\Filament\Resources\Excursions\Schemas;

use App\Filament\Support\PriceFields;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExcursionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Escursione')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('destination_id')
                            ->label('Destinazione')
                            ->relationship('destination', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('duration')
                            ->label('Durata')
                            ->placeholder('Es. Intera giornata, 4 ore'),
                        Toggle::make('is_active')
                            ->label('Attiva')
                            ->default(true)
                            ->inline(false),
                        Textarea::make('description')
                            ->label('Descrizione')
                            ->rows(3)
                            ->columnSpanFull(),
                        FileUpload::make('image')
                            ->label('Immagine')
                            ->image()
                            ->disk('public')
                            ->directory('excursions')
                            ->columnSpanFull(),
                    ]),
                Section::make('Prezzi per persona')
                    ->columnSpanFull()
                    ->schema([
                        PriceFields::pair('adult_price', 'Adulto', required: true),
                        PriceFields::pair('child_price', 'Bambino', helperText: 'Se vuoto si applica il prezzo adulto. Gli infant non pagano.'),
                    ]),
            ]);
    }
}
