<?php

namespace App\Filament\Resources\Hotels\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HotelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
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
                            ->label('Nome hotel')
                            ->required()
                            ->maxLength(255),
                        Select::make('stars')
                            ->label('Stelle')
                            ->options([1 => '1 ★', 2 => '2 ★', 3 => '3 ★', 4 => '4 ★', 5 => '5 ★']),
                        TextInput::make('address')
                            ->label('Indirizzo / località')
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->helperText('Lascia vuoto per generarlo automaticamente.')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Attivo')
                            ->default(true)
                            ->inline(false),
                        Textarea::make('description')
                            ->label('Descrizione')
                            ->rows(4)
                            ->columnSpanFull(),
                        TagsInput::make('amenities')
                            ->label('Servizi')
                            ->placeholder('Es. Piscina, Spa, Wi-Fi...')
                            ->columnSpanFull(),
                        FileUpload::make('images')
                            ->label('Foto')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->disk('public')
                            ->directory('hotels')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
