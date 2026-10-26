<?php

use Illuminate\Support\Facades\Schedule;

// Richiede sul server l'attività pianificata: php artisan schedule:run (ogni minuto).
Schedule::command('bookings:expire-options')->everyMinute()->withoutOverlapping();
