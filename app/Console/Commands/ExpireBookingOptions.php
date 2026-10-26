<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

class ExpireBookingOptions extends Command
{
    protected $signature = 'bookings:expire-options';

    protected $description = 'Segna come scadute le opzioni oltre il tempo limite';

    public function handle(BookingService $bookings): int
    {
        $expired = $bookings->expireOptions();

        $this->info("Opzioni scadute: {$expired}");

        return self::SUCCESS;
    }
}
