<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['package', 'agency'])->withCount('participants'))
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Booking $record) => route('filament.admin.resources.bookings.view', $record))
            ->columns([
                TextColumn::make('code')
                    ->label('Codice')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),
                TextColumn::make('created_at')
                    ->label('Ricevuta')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('package.title')
                    ->label('Pacchetto')
                    ->wrap()
                    ->limit(40),
                TextColumn::make('departure_date')
                    ->label('Partenza')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('contact_last_name')
                    ->label('Referente')
                    ->formatStateUsing(fn (Booking $record) => $record->contactFullName())
                    ->description(fn (Booking $record) => $record->contact_phone)
                    ->searchable(['contact_first_name', 'contact_last_name', 'contact_email', 'contact_phone']),
                TextColumn::make('agency.company_name')
                    ->label('Agenzia')
                    ->placeholder('Ospite')
                    ->searchable(),
                TextColumn::make('participants_count')
                    ->label('Pax')
                    ->alignCenter(),
                TextColumn::make('total_amount')
                    ->label('Totale')
                    ->money('EUR')
                    ->description(fn (Booking $record) => $record->isNetPrice() ? 'netto' : 'lordo')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(BookingStatus::class),
                TernaryFilter::make('agency')
                    ->label('Provenienza')
                    ->trueLabel('Agenzie')
                    ->falseLabel('Ospiti')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('agency_id'),
                        false: fn (Builder $query) => $query->whereNull('agency_id'),
                    ),
                SelectFilter::make('package')
                    ->label('Pacchetto')
                    ->relationship('package', 'title')
                    ->searchable(),
                Filter::make('departure_date')
                    ->label('Partenza')
                    ->schema([
                        DatePicker::make('from')->label('Partenza dal'),
                        DatePicker::make('until')->label('Partenza al'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('departure_date', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('departure_date', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
