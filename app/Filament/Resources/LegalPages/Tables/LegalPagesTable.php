<?php

namespace App\Filament\Resources\LegalPages\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class LegalPagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->color('gray'),
                IconColumn::make('requires_acceptance')
                    ->label('Da accettare')
                    ->boolean(),
                ToggleColumn::make('is_active')
                    ->label('Pubblicato'),
                TextColumn::make('updated_at')
                    ->label('Ultima modifica')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
