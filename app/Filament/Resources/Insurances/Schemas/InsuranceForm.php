<?php

namespace App\Filament\Resources\Insurances\Schemas;

use App\Enums\InsuranceType;
use App\Filament\Support\PriceFields;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InsuranceForm
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
                        Select::make('type')
                            ->label('Tipo')
                            ->options(InsuranceType::class)
                            ->required(),
                        Textarea::make('description')
                            ->label('Descrizione / coperture')
                            ->rows(3)
                            ->columnSpanFull(),
                        PriceFields::money('price', 'Prezzo per persona', required: true)
                            ->helperText('Applicato a ogni passeggero pagante (infant esclusi).'),
                        Toggle::make('is_active')
                            ->label('Attiva')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
