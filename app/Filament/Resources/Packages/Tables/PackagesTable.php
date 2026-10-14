<?php

namespace App\Filament\Resources\Packages\Tables;

use App\Enums\PackageType;
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with('periods'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('cover_image')
                    ->label('')
                    ->disk('public'),
                TextColumn::make('title')
                    ->label('Pacchetto')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(fn (Package $record) => $record->location),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('destination.name')
                    ->label('Destinazione')
                    ->sortable(),
                TextColumn::make('nights')
                    ->label('Notti')
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
                    ->label('Da (lordo)')
                    ->state(fn (Package $record) => $record->startingPrice(false))
                    ->money('EUR')
                    ->description(fn (Package $record) => $record->price_unit->getLabel())
                    ->placeholder('Nessun periodo'),
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
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(PackageType::class),
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
                            $replica->excursions()->sync($record->excursions()->pluck('excursions.id'));
                            $replica->insurances()->sync($record->insurances()->pluck('insurances.id'));
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
