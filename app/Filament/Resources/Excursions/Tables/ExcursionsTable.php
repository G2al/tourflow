<?php

namespace App\Filament\Resources\Excursions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExcursionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Escursione')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('destination.name')
                    ->label('Destinazione')
                    ->sortable(),
                TextColumn::make('duration')
                    ->label('Durata')
                    ->toggleable(),
                TextColumn::make('adult_price_gross')
                    ->label('Adulto lordo')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('adult_price_net')
                    ->label('Adulto netto')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('child_price_gross')
                    ->label('Bambino lordo')
                    ->money('EUR')
                    ->placeholder('= adulto')
                    ->toggleable(),
                ToggleColumn::make('is_active')
                    ->label('Attiva'),
            ])
            ->filters([
                SelectFilter::make('destination')
                    ->label('Destinazione')
                    ->relationship('destination', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
