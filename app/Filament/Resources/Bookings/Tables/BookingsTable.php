<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
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
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['items', 'agency'])
                ->withCount('passengers')
                ->withMin('items as first_service_date', 'start_date'))
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Booking $record) => route('filament.admin.resources.bookings.view', $record))
            ->columns([
                TextColumn::make('code')
                    ->label('Pratica')
                    ->searchable()
                    ->weight('bold')
                    ->copyable()
                    ->description(fn (Booking $record) => $record->created_at->format('d/m/Y H:i')),
                TextColumn::make('services')
                    ->label('Servizi')
                    ->state(fn (Booking $record) => $record->items->map(fn ($item) => "{$item->title} ({$item->mode->getLabel()})")->all())
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->wrap(),
                TextColumn::make('first_service_date')
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
                    ->placeholder('Cliente diretto')
                    ->searchable(),
                TextColumn::make('passengers_count')
                    ->label('Pax')
                    ->alignCenter(),
                TextColumn::make('total')
                    ->label('Totale')
                    ->money('EUR')
                    ->description(fn (Booking $record) => $record->isAgencyBooking() ? 'dovuto € '.number_format((float) $record->amount_due, 2, ',', '.') : null)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->description(fn (Booking $record) => $record->status === BookingStatus::Option && $record->option_expires_at
                        ? ($record->isOptionExpired() ? 'scaduta' : 'scade '.$record->option_expires_at->diffForHumans())
                        : null),
                IconColumn::make('proforma_viewed_at')
                    ->label('Proforma letta')
                    ->state(fn (Booking $record) => $record->proforma_viewed_at !== null)
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->multiple()
                    ->options(BookingStatus::class),
                TernaryFilter::make('agency')
                    ->label('Provenienza')
                    ->trueLabel('Agenzie')
                    ->falseLabel('Clienti diretti')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('agency_id'),
                        false: fn (Builder $query) => $query->whereNull('agency_id'),
                    ),
                Filter::make('first_service_date')
                    ->label('Partenza')
                    ->schema([
                        DatePicker::make('from')->label('Partenza dal'),
                        DatePicker::make('until')->label('Partenza al'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereHas('items', fn ($i) => $i->whereDate('start_date', '>=', $date)))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereHas('items', fn ($i) => $i->whereDate('start_date', '<=', $date)))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
