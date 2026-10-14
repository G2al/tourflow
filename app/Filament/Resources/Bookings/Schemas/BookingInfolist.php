<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Models\Booking;
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
                        Section::make('Viaggio')
                            ->columns(3)
                            ->schema([
                                TextEntry::make('package.title')
                                    ->label('Pacchetto')
                                    ->columnSpan(2),
                                TextEntry::make('package.destination.name')
                                    ->label('Destinazione'),
                                TextEntry::make('package.hotel.name')
                                    ->label('Hotel')
                                    ->placeholder('-'),
                                TextEntry::make('departure_date')
                                    ->label('Partenza')
                                    ->date('d/m/Y'),
                                TextEntry::make('return_date')
                                    ->label('Rientro')
                                    ->date('d/m/Y'),
                                TextEntry::make('period.name')
                                    ->label('Periodo tariffario')
                                    ->placeholder('-'),
                                TextEntry::make('package.board')
                                    ->label('Trattamento')
                                    ->placeholder('-'),
                                TextEntry::make('package.nights')
                                    ->label('Notti'),
                            ]),
                        Section::make('Partecipanti')
                            ->schema([
                                RepeatableEntry::make('participants')
                                    ->hiddenLabel()
                                    ->columns(4)
                                    ->schema([
                                        TextEntry::make('full_name')
                                            ->label('Nome')
                                            ->state(fn ($record) => "{$record->first_name} {$record->last_name}")
                                            ->badge(fn ($record) => $record->is_lead)
                                            ->helperText(fn ($record) => $record->is_lead ? 'Capogruppo' : null),
                                        TextEntry::make('birth_date')
                                            ->label('Data di nascita')
                                            ->date('d/m/Y'),
                                        TextEntry::make('age_category')
                                            ->label('Categoria')
                                            ->badge(),
                                        TextEntry::make('price')
                                            ->label('Quota')
                                            ->money('EUR'),
                                        TextEntry::make('gender')
                                            ->label('Sesso'),
                                        TextEntry::make('tax_code')
                                            ->label('Codice fiscale')
                                            ->placeholder('-')
                                            ->copyable(),
                                        TextEntry::make('nationality')
                                            ->label('Nazionalità')
                                            ->placeholder('-'),
                                        TextEntry::make('document')
                                            ->label('Documento')
                                            ->state(fn ($record) => $record->document_number
                                                ? trim(($record->document_type?->getLabel() ?? '').' '.$record->document_number.($record->document_expiry ? ' (scad. '.$record->document_expiry->format('d/m/Y').')' : ''))
                                                : null)
                                            ->placeholder('-'),
                                    ]),
                            ]),
                        Section::make('Extra')
                            ->visible(fn (Booking $record) => $record->extras->isNotEmpty())
                            ->schema([
                                RepeatableEntry::make('extras')
                                    ->hiddenLabel()
                                    ->columns(4)
                                    ->schema([
                                        TextEntry::make('type')
                                            ->label('Tipo')
                                            ->formatStateUsing(fn (string $state) => $state === 'excursion' ? 'Escursione' : 'Assicurazione')
                                            ->badge(),
                                        TextEntry::make('name')
                                            ->label('Nome'),
                                        TextEntry::make('quantity')
                                            ->label('Persone'),
                                        TextEntry::make('total_price')
                                            ->label('Totale')
                                            ->money('EUR'),
                                    ]),
                            ]),
                        Section::make('Note')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('notes')
                                    ->label('Note del cliente')
                                    ->placeholder('-'),
                                TextEntry::make('admin_notes')
                                    ->label('Note interne')
                                    ->placeholder('-'),
                            ]),
                    ]),
                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        Section::make('Riepilogo')
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Codice')
                                    ->weight('bold')
                                    ->copyable(),
                                TextEntry::make('status')
                                    ->label('Stato')
                                    ->badge(),
                                TextEntry::make('created_at')
                                    ->label('Ricevuta il')
                                    ->dateTime('d/m/Y H:i'),
                                TextEntry::make('price_type')
                                    ->label('Tariffa applicata')
                                    ->formatStateUsing(fn (string $state) => $state === 'net' ? 'Netto agenzia' : 'Lordo (pubblico)'),
                                TextEntry::make('total_amount')
                                    ->label('Totale')
                                    ->money('EUR')
                                    ->size('lg')
                                    ->weight('bold'),
                                TextEntry::make('total_gross')
                                    ->label('Totale al pubblico')
                                    ->money('EUR')
                                    ->visible(fn (Booking $record) => $record->isNetPrice()),
                                TextEntry::make('agency_margin')
                                    ->label('Margine agenzia')
                                    ->state(fn (Booking $record) => $record->total_gross - $record->total_amount)
                                    ->money('EUR')
                                    ->visible(fn (Booking $record) => $record->isNetPrice()),
                            ]),
                        Section::make('Referente')
                            ->schema([
                                TextEntry::make('contact_name')
                                    ->label('Nome')
                                    ->state(fn (Booking $record) => $record->contactFullName()),
                                TextEntry::make('contact_email')
                                    ->label('Email')
                                    ->copyable(),
                                TextEntry::make('contact_phone')
                                    ->label('Telefono')
                                    ->copyable(),
                            ]),
                        Section::make('Agenzia')
                            ->visible(fn (Booking $record) => $record->agency !== null)
                            ->schema([
                                TextEntry::make('agency.company_name')
                                    ->label('Ragione sociale'),
                                TextEntry::make('agency.vat_number')
                                    ->label('P.IVA')
                                    ->copyable(),
                                TextEntry::make('agency_address')
                                    ->label('Indirizzo')
                                    ->state(fn (Booking $record) => $record->agency?->fullAddress()),
                                TextEntry::make('user.name')
                                    ->label('Operatore'),
                                TextEntry::make('agency_reference')
                                    ->label('Rif. pratica agenzia')
                                    ->placeholder('-'),
                            ]),
                    ]),
            ]);
    }
}
