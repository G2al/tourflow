<?php

namespace App\Filament\Resources\Packages\Tables;

use App\Enums\PackageOffer;
use App\Models\Package;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['periods', 'bookableFlights']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('cover_image')
                    ->label('')
                    ->disk('public'),
                TextColumn::make('title')
                    ->label('Pacchetto')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('offer')
                    ->label('Cosa vende')
                    ->badge()
                    ->description(fn (Package $record) => $record->offer === PackageOffer::HotelOnly && $record->flight_hotel_enabled ? 'volo facoltativo' : null),
                TextColumn::make('destination.name')
                    ->label('Destinazione')
                    ->sortable(),
                TextColumn::make('board')
                    ->label('Trattamento')
                    ->toggleable(),
                TextColumn::make('valid_from')
                    ->label('Validità')
                    ->formatStateUsing(fn (Package $record) => $record->valid_from->format('d/m/Y').' - '.$record->valid_to->format('d/m/Y'))
                    ->color(fn (Package $record) => $record->valid_to->isPast() ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('starting_price')
                    ->label('A partire da')
                    ->state(fn (Package $record) => $record->startingPrice())
                    ->money('EUR')
                    ->description(fn (Package $record) => match ($record->priceBasis()) {
                        'per_night' => 'a persona a notte',
                        'per_service' => 'a servizio',
                        default => $record->offer->includesFlight() ? "a persona, {$record->displayNights()} notti con volo" : 'a persona',
                    })
                    ->placeholder('Prezzi o voli mancanti'),
                TextColumn::make('badge')
                    ->label('Badge')
                    ->badge()
                    ->color('warning')
                    ->placeholder('-')
                    ->toggleable(),
                ToggleColumn::make('is_featured')
                    ->label('In evidenza'),
                ToggleColumn::make('is_active')
                    ->label('Pubblicato'),
            ])
            ->filters([
                SelectFilter::make('offer')
                    ->label('Cosa vende')
                    ->options(PackageOffer::class),
                SelectFilter::make('destination')
                    ->label('Destinazione')
                    ->relationship('destination', 'name'),
                TernaryFilter::make('is_active')
                    ->label('Pubblicato'),
                TernaryFilter::make('expired')
                    ->label('Scaduti')
                    ->queries(
                        true: fn (Builder $query) => $query->whereDate('valid_to', '<', today()),
                        false: fn (Builder $query) => $query->whereDate('valid_to', '>=', today()),
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    ReplicateAction::make()
                        ->label('Duplica')
                        ->excludeAttributes(['slug'])
                        ->beforeReplicaSaved(function (Package $replica) {
                            $replica->title .= ' (copia)';
                            $replica->is_active = false;
                        })
                        ->after(function (Package $record, Package $replica) {
                            foreach ($record->periods as $period) {
                                $replica->periods()->create($period->replicate(['package_id'])->toArray());
                            }
                        })
                        ->successNotificationTitle('Pacchetto duplicato (non pubblicato)'),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
