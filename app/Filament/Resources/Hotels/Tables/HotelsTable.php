<?php

namespace App\Filament\Resources\Hotels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class HotelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Hotel')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('stars')
                    ->label('Stelle')
                    ->formatStateUsing(fn (?int $state) => $state ? str_repeat('★', $state) : '-')
                    ->sortable(),
                TextColumn::make('destination.name')
                    ->label('Destinazione')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('address')
                    ->label('Località')
                    ->toggleable(),
                TextColumn::make('packages_count')
                    ->label('Pacchetti')
                    ->counts('packages')
                    ->badge(),
                ToggleColumn::make('is_active')
                    ->label('Attivo'),
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
