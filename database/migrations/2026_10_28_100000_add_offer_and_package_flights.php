<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - "offer": cosa vende il pacchetto (solo soggiorno, volo + hotel, pacchetto
 *   completo, transfer, escursione);
 * - voli scelti per ogni pacchetto, al posto di tutti i voli della destinazione.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('offer', 20)->default('hotel_only')->after('type')->index();
        });

        Schema::create('flight_package', function (Blueprint $table) {
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flight_id')->constrained()->cascadeOnDelete();
            $table->primary(['package_id', 'flight_id']);
        });

        // Pacchetti esistenti: offerta ricavata dal tipo e dalle modalità;
        // ai volo + hotel si abbinano i voli della loro destinazione (comportamento precedente).
        foreach (DB::table('packages')->get() as $package) {
            $offer = match (true) {
                $package->type === 'transfer' => 'transfer',
                $package->type === 'excursion' => 'excursion',
                $package->flight_hotel_enabled && ! $package->hotel_only_enabled => $package->transfer_included ? 'full_package' : 'flight_hotel',
                default => 'hotel_only',
            };

            DB::table('packages')->where('id', $package->id)->update(['offer' => $offer]);

            if ($package->flight_hotel_enabled) {
                $flightIds = DB::table('flights')->where('destination_id', $package->destination_id)->pluck('id');
                DB::table('flight_package')->insert($flightIds->map(fn ($id) => ['package_id' => $package->id, 'flight_id' => $id])->all());
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flight_package');

        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex(['offer']);
            $table->dropColumn('offer');
        });
    }
};
