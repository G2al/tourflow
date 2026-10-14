<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestBookings extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Ultime prenotazioni';

    public function table(Table $table): Table
    {
        return $table
            ->query(Booking::query()->with(['package', 'agency'])->latest()->limit(10))
            ->paginated(false)
            ->recordUrl(fn (Booking $record) => BookingResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('code')
                    ->label('Codice')
                    ->weight('bold'),
                TextColumn::make('created_at')
                    ->label('Ricevuta')
                    ->since(),
                TextColumn::make('package.title')
                    ->label('Pacchetto')
                    ->limit(40),
                TextColumn::make('departure_date')
                    ->label('Partenza')
                    ->date('d/m/Y'),
                TextColumn::make('agency.company_name')
                    ->label('Agenzia')
                    ->placeholder('Ospite'),
                TextColumn::make('total_amount')
                    ->label('Totale')
                    ->money('EUR'),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge(),
            ]);
    }
}
