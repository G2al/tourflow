<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

class SendOptionReminders extends Command
{
    protected $signature = 'bookings:option-reminders';

    protected $description = 'Invia i promemoria di scadenza delle opzioni';

    public function handle(BookingService $bookings): int
    {
        $this->info('Promemoria inviati: '.$bookings->sendOptionReminders());

        return self::SUCCESS;
    }
}
