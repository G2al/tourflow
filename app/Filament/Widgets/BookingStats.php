<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
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
            Stat::make('Prenotazioni in attesa', Booking::where('status', BookingStatus::Pending)->count())
                ->description('Da gestire')
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('Prenotazioni del mese', (clone $thisMonth)->count())
                ->description('Confermate: '.(clone $thisMonth)->where('status', BookingStatus::Confirmed)->count())
                ->icon('heroicon-o-clipboard-document-check'),
            Stat::make('Pacchetti pubblicati', Package::published()->count())
                ->icon('heroicon-o-globe-europe-africa'),
            Stat::make('Agenzie registrate', User::where('role', UserRole::Agency)->count())
                ->description('Nuove questo mese: '.User::where('role', UserRole::Agency)->where('created_at', '>=', now()->startOfMonth())->count())
                ->icon('heroicon-o-building-storefront'),
        ];
    }
}
