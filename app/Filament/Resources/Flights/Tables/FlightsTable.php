<?php

namespace App\Filament\Resources\Flights\Tables;

use App\Enums\Airport;
use App\Models\Flight;
use App\Services\Inventory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FlightsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('outbound_date')
            ->columns([
                TextColumn::make('outbound_date')
                    ->label('Andata')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('return_date')
                    ->label('Ritorno')
                    ->date('d/m/Y')
                    ->description(fn (Flight $record) => $record->nights().' notti'),
                TextColumn::make('departure_airport')
                    ->label('Da')
                    ->badge(),
                TextColumn::make('destination.name')
                    ->label('Destinazione')
                    ->sortable(),
                TextColumn::make('adult_price')
                    ->label('Adulto')
                    ->money('EUR'),
                TextColumn::make('seats')
                    ->label('Posti'),
                TextColumn::make('seats_available')
                    ->label('Liberi')
                    ->state(fn (Flight $record) => app(Inventory::class)->seatsAvailable($record))
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'danger'),
                ToggleColumn::make('is_active')
                    ->label('Attivo'),
            ])
            ->filters([
                SelectFilter::make('destination')
                    ->label('Destinazione')
                    ->relationship('destination', 'name'),
                SelectFilter::make('departure_airport')
                    ->label('Aeroporto')
                    ->options(Airport::class),
                TernaryFilter::make('upcoming')
                    ->label('Futuri')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->whereDate('outbound_date', '>=', today()),
                        false: fn (Builder $query) => $query->whereDate('outbound_date', '<', today()),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                ReplicateAction::make()
                    ->label('Duplica')
                    ->successNotificationTitle('Volo duplicato'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
