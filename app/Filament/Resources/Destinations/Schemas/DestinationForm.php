<?php

namespace App\Filament\Resources\Destinations\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DestinationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('country')
                            ->label('Paese')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->helperText('Lascia vuoto per generarlo automaticamente dal nome.')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('sort_order')
                            ->label('Ordinamento')
                            ->numeric()
                            ->default(0),
                        Textarea::make('description')
                            ->label('Descrizione')
                            ->rows(4)
                            ->columnSpanFull(),
                        FileUpload::make('cover_image')
                            ->label('Immagine di copertina')
                            ->image()
                            ->disk('public')
                            ->directory('destinations')
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Attiva')
                            ->default(true),
                    ]),
            ]);
    }
}
