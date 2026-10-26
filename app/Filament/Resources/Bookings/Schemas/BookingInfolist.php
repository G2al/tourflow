<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Models\Booking;
use App\Models\BookingItem;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(2)
                    ->schema([
                        self::services(),
                        self::passengers(),
                        Section::make('Assicurazioni e garanzie')
                            ->visible(fn (Booking $record) => $record->insurances->isNotEmpty() || $record->price_lock)
                            ->schema([
                                RepeatableEntry::make('insurances')
                                    ->hiddenLabel()
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('name')->label('Assicurazione'),
                                        TextEntry::make('quantity')
                                            ->label('Persone')
                                            ->formatStateUsing(fn ($state, $record) => "{$state} × € ".number_format((float) $record->unit_price, 2, ',', '.')),
                                        TextEntry::make('total')->label('Totale')->money('EUR'),
                                    ]),
                                TextEntry::make('price_lock_amount')
                                    ->label('Blocca prezzo')
                                    ->money('EUR')
                                    ->visible(fn (Booking $record) => $record->price_lock),
                            ]),
                        Section::make('Note')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('notes')->label('Note del cliente')->placeholder('-'),
                                TextEntry::make('admin_notes')->label('Note interne')->placeholder('-'),
                            ]),
                    ]),
                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        self::summary(),
                        self::timeline(),
                        Section::make('Referente')
                            ->schema([
                                TextEntry::make('contact_name')
                                    ->label('Nome')
                                    ->state(fn (Booking $record) => $record->contactFullName()),
                                TextEntry::make('contact_email')->label('Email')->copyable(),
                                TextEntry::make('contact_phone')->label('Telefono')->copyable(),
                            ]),
                        Section::make('Agenzia')
                            ->visible(fn (Booking $record) => $record->agency !== null)
                            ->schema([
                                TextEntry::make('agency.company_name')->label('Ragione sociale'),
                                TextEntry::make('agency.vat_number')->label('P.IVA')->copyable(),
                                TextEntry::make('agency_contacts')
                                    ->label('Contatti')
                                    ->state(fn (Booking $record) => trim($record->agency?->email.' · '.$record->agency?->phone, ' ·')),
                                TextEntry::make('agency_address')
                                    ->label('Indirizzo')
                                    ->state(fn (Booking $record) => $record->agency?->fullAddress()),
                                TextEntry::make('user.name')->label('Operatore'),
                                TextEntry::make('agency_reference')->label('Rif. pratica agenzia')->placeholder('-'),
                            ]),
                    ]),
            ]);
    }

    private static function services(): Section
    {
        return Section::make('Servizi')
            ->schema([
                RepeatableEntry::make('items')
                    ->hiddenLabel()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('title')
                            ->label('Servizio')
                            ->weight('bold')
                            ->columnSpan(2),
                        TextEntry::make('mode')
                            ->label('Tipo')
                            ->badge(),
                        TextEntry::make('dates')
                            ->label('Date')
                            ->state(fn (BookingItem $record) => $record->nights
                                ? "{$record->start_date->format('d/m/Y')} - {$record->end_date->format('d/m/Y')} ({$record->nights} notti)"
                                : $record->start_date->format('d/m/Y')),
                        TextEntry::make('flight_label')
                            ->label('Volo')
                            ->state(fn (BookingItem $record) => $record->flight
                                ? $record->flight->label()." · {$record->seats} posti"
                                : null)
                            ->placeholder('-'),
                        TextEntry::make('total')
                            ->label('Totale')
                            ->money('EUR'),
                        TextEntry::make('rooms_label')
                            ->label('Camere')
                            ->state(fn (BookingItem $record) => self::roomsDescription($record))
                            ->listWithLineBreaks()
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('lines_label')
                            ->label('Dettaglio prezzo')
                            ->state(fn (BookingItem $record) => self::linesDescription($record))
                            ->listWithLineBreaks()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function passengers(): Section
    {
        return Section::make('Passeggeri')
            ->schema([
                RepeatableEntry::make('passengers')
                    ->hiddenLabel()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('full_name')
                            ->label('Nome')
                            ->state(fn ($record) => ($record->position + 1).'. '.$record->fullName())
                            ->helperText(fn ($record) => $record->is_lead ? 'Capogruppo' : null),
                        TextEntry::make('birth_date')->label('Data di nascita')->date('d/m/Y'),
                        TextEntry::make('age_category')->label('Categoria')->badge(),
                        TextEntry::make('gender')->label('Sesso')->placeholder('-'),
                        TextEntry::make('tax_code')->label('Codice fiscale')->placeholder('-')->copyable(),
                        TextEntry::make('nationality')->label('Nazionalità')->placeholder('-'),
                        TextEntry::make('document')
                            ->label('Documento')
                            ->state(fn ($record) => $record->document_number
                                ? trim(($record->document_type?->getLabel() ?? '').' '.$record->document_number.($record->document_expiry ? ' (scad. '.$record->document_expiry->format('d/m/Y').')' : ''))
                                : null)
                            ->placeholder('-')
                            ->columnSpan(2),
                    ]),
            ]);
    }

    private static function summary(): Section
    {
        return Section::make('Riepilogo')
            ->schema([
                TextEntry::make('code')->label('Pratica')->weight('bold')->copyable(),
                TextEntry::make('status')->label('Stato')->badge(),
                TextEntry::make('origin')
                    ->label('Provenienza')
                    ->state(fn (Booking $record) => $record->isAgencyBooking() ? 'Agenzia' : 'Cliente diretto'),
                TextEntry::make('items_total')->label('Servizi')->money('EUR'),
                TextEntry::make('insurances_total')->label('Assicurazioni')->money('EUR')
                    ->visible(fn (Booking $record) => (float) $record->insurances_total > 0),
                TextEntry::make('total')->label('Totale pratica')->money('EUR')->size('lg')->weight('bold'),
                TextEntry::make('fee_amount')
                    ->label(fn (Booking $record) => 'Fee agenzia ('.rtrim(rtrim(number_format((float) $record->fee_percent, 2, ',', ''), '0'), ',').'%)')
                    ->money('EUR')
                    ->visible(fn (Booking $record) => $record->isAgencyBooking()),
                TextEntry::make('amount_due')->label('Importo dovuto')->money('EUR')->weight('bold'),
                TextEntry::make('deposit_amount')
                    ->label(fn (Booking $record) => 'Acconto ('.rtrim(rtrim(number_format((float) $record->deposit_percent, 2, ',', ''), '0'), ',').'%)')
                    ->money('EUR'),
            ]);
    }

    private static function timeline(): Section
    {
        $dateTime = fn (string $field, string $label) => TextEntry::make($field)
            ->label($label)
            ->dateTime('d/m/Y H:i')
            ->visible(fn (Booking $record) => $record->{$field} !== null);

        return Section::make('Cronologia')
            ->schema([
                TextEntry::make('created_at')->label('Creata il')->dateTime('d/m/Y H:i'),
                TextEntry::make('quote_valid_until')
                    ->label('Preventivo valido fino al')
                    ->date('d/m/Y')
                    ->visible(fn (Booking $record) => $record->quote_valid_until !== null),
                $dateTime('option_expires_at', 'Opzione valida fino al'),
                $dateTime('confirmed_at', 'Confermata il'),
                $dateTime('proforma_sent_at', 'Proforma generata il'),
                TextEntry::make('proforma_viewed_at')
                    ->label('Proforma aperta dall\'agenzia')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Non ancora')
                    ->visible(fn (Booking $record) => $record->proforma_sent_at !== null),
                $dateTime('cancelled_at', 'Annullata il'),
                TextEntry::make('terms_accepted_at')
                    ->label('Condizioni accettate il')
                    ->dateTime('d/m/Y H:i')
                    ->helperText(fn (Booking $record) => $record->terms_accepted_ip ? "IP {$record->terms_accepted_ip}" : null)
                    ->visible(fn (Booking $record) => $record->terms_accepted_at !== null),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function roomsDescription(BookingItem $item): array
    {
        $passengers = $item->booking->passengers->keyBy('position');

        return collect($item->breakdown['rooms'] ?? [])->map(fn (array $room) => 'Camera '.($room['index'] + 1)
            ." · {$room['room_type']}: "
            .collect($room['passengers'])->map(fn ($p) => $passengers->get($p)?->fullName())->join(', ')
            .' — € '.number_format((float) $room['total'], 2, ',', '.'))->all();
    }

    /**
     * @return array<int, string>
     */
    private static function linesDescription(BookingItem $item): array
    {
        $passengers = $item->booking->passengers->keyBy('position');
        $lines = [...collect($item->breakdown['rooms'] ?? [])->pluck('lines')->flatten(1)->all(), ...($item->breakdown['lines'] ?? [])];

        return array_map(fn (array $line) => $line['label']
            .($line['passenger'] !== null ? ' · '.$passengers->get($line['passenger'])?->fullName() : '')
            .' — € '.number_format((float) $line['amount'], 2, ',', '.'), $lines);
    }
}
