<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BookingStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $thisMonth = Booking::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);

        return [
            Stat::make('Confermate da incassare', Booking::whereIn('status', BookingResource::toHandle())->count())
                ->description('In attesa di acconto')
                ->icon('heroicon-o-banknotes')
                ->color('warning'),
            Stat::make('Opzioni attive', Booking::where('status', BookingStatus::Option)->where('option_expires_at', '>', now())->count())
                ->description('In scadenza nelle prossime 24 ore: '.Booking::where('status', BookingStatus::Option)->whereBetween('option_expires_at', [now(), now()->addDay()])->count())
                ->icon('heroicon-o-clock'),
            Stat::make('Pratiche del mese', (clone $thisMonth)->count())
                ->description('Preventivi: '.(clone $thisMonth)->where('status', BookingStatus::Quote)->count())
                ->icon('heroicon-o-clipboard-document-check'),
            Stat::make('Pacchetti pubblicati', Package::published()->count())
                ->icon('heroicon-o-globe-europe-africa'),
            Stat::make('Agenzie registrate', User::where('role', UserRole::Agency)->count())
                ->description('Nuove questo mese: '.User::where('role', UserRole::Agency)->where('created_at', '>=', now()->startOfMonth())->count())
                ->icon('heroicon-o-building-storefront'),
        ];
    }
}
