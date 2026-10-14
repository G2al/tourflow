<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('agency.company_name')
                    ->label('Agenzia')
                    ->searchable()
                    ->placeholder('-')
                    ->description(fn ($record) => $record->agency ? 'P.IVA '.$record->agency->vat_number : null),
                TextColumn::make('name')
                    ->label('Operatore')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('role')
                    ->label('Ruolo')
                    ->badge(),
                TextColumn::make('agency.city')
                    ->label('Città')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('bookings_count')
                    ->label('Prenotazioni')
                    ->counts('bookings')
                    ->badge(),
                ToggleColumn::make('is_active')
                    ->label('Attivo'),
                TextColumn::make('created_at')
                    ->label('Registrato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Ruolo')
                    ->options(UserRole::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
