<?php

namespace App\Filament\Resources\LegalPages\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LegalPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->label('Titolo')
                            ->placeholder('Es. Condizioni generali di vendita')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->helperText('Lascia vuoto per generarlo dal titolo.')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        RichEditor::make('content')
                            ->label('Testo')
                            ->required()
                            ->columnSpanFull(),
                        Toggle::make('requires_acceptance')
                            ->label('Da accettare per opzione e conferma')
                            ->helperText('Data, ora e versione dell\'accettazione vengono salvate nella pratica.')
                            ->default(true),
                        Toggle::make('is_active')
                            ->label('Pubblicato')
                            ->default(true),
                        TextInput::make('sort_order')
                            ->label('Ordinamento')
                            ->numeric()
                            ->default(0),
                    ]),
            ]);
    }
}
