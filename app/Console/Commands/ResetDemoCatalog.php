<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\LegalPagesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Svuota il catalogo (destinazioni, hotel, camere, voli, pacchetti,
 * assicurazioni) e ricarica quello dimostrativo. Non tocca utenti, agenzie,
 * impostazioni e testi legali già scritti.
 */
class ResetDemoCatalog extends Command
{
    protected $signature = 'demo:reset-catalog {--force : Procede anche se esistono pratiche (che verranno eliminate)}';

    protected $description = 'Ricarica il catalogo dimostrativo (cancella quello attuale)';

    private const TABLES = [
        'booking_insurances', 'booking_item_rooms', 'booking_items', 'booking_passengers', 'bookings',
        'room_allotments', 'room_types', 'package_periods', 'packages', 'flights', 'hotels', 'destinations', 'insurances',
    ];

    public function handle(): int
    {
        if (Booking::exists() && ! $this->option('force')) {
            $this->error('Esistono pratiche: il catalogo non viene toccato. Usa --force per eliminarle insieme al catalogo.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Il catalogo attuale verrà cancellato. Procedere?', true)) {
            return self::FAILURE;
        }

        Schema::disableForeignKeyConstraints();
        foreach (self::TABLES as $table) {
            DB::table($table)->truncate();
        }
        Schema::enableForeignKeyConstraints();

        $this->call('db:seed', ['--class' => LegalPagesSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => DemoCatalogSeeder::class, '--force' => true]);

        $this->info('Catalogo dimostrativo ricaricato.');

        return self::SUCCESS;
    }
}
