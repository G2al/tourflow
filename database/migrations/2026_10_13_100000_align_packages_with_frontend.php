<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allinea i pacchetti al modello dati del frontend:
 * - tipo di prodotto (soggiorno, transfer, escursione) e unità di prezzo;
 * - contenuti editoriali (località, badge, punti forti, programma, ecc.);
 * - trattamento come testo libero, incluso/non incluso come liste;
 * - rimozione dei campi volo (il tour operator vende solo servizi a terra).
 */
return new class extends Migration
{
    private const BOARD_LABELS = [
        'RO' => 'Solo pernottamento',
        'BB' => 'Pernottamento e colazione',
        'HB' => 'Mezza pensione',
        'FB' => 'Pensione completa',
        'AI' => 'All Inclusive',
    ];

    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('type', 20)->default('stay')->after('hotel_id')->index();
            $table->string('price_unit', 20)->default('per_person')->after('type');
            $table->string('location')->nullable()->after('title');
            $table->string('badge', 50)->nullable()->after('location');
            $table->string('board')->nullable()->after('nights');
            $table->string('accommodation')->nullable()->after('board');
            $table->string('period_label')->nullable()->after('valid_to');
            $table->json('departures')->nullable()->after('period_label');
            $table->json('highlights')->nullable()->after('description');
            $table->json('itinerary')->nullable()->after('highlights');
            $table->json('included')->nullable()->after('itinerary');
            $table->json('excluded')->nullable()->after('included');
        });

        DB::table('packages')->orderBy('id')->each(function (object $package) {
            DB::table('packages')->where('id', $package->id)->update([
                'board' => self::BOARD_LABELS[$package->board_type] ?? $package->board_type,
                'included' => json_encode($this->lines($package->includes)),
                'excluded' => json_encode($this->lines($package->excludes)),
                'description' => $package->description === null ? null : trim(html_entity_decode(strip_tags(
                    str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $package->description)
                ))),
            ]);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn([
                'board_type',
                'includes',
                'excludes',
                'flight_included',
                'departure_airport',
                'flight_price_gross',
                'flight_price_net',
                'airport_taxes',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('board_type', 5)->default('AI');
            $table->text('includes')->nullable();
            $table->text('excludes')->nullable();
            $table->boolean('flight_included')->default(true);
            $table->string('departure_airport')->nullable();
            $table->decimal('flight_price_gross', 10, 2)->nullable();
            $table->decimal('flight_price_net', 10, 2)->nullable();
            $table->decimal('airport_taxes', 10, 2)->default(0);
        });

        DB::table('packages')->orderBy('id')->each(function (object $package) {
            DB::table('packages')->where('id', $package->id)->update([
                'board_type' => array_search($package->board, self::BOARD_LABELS, true) ?: 'AI',
                'includes' => implode("\n", json_decode($package->included ?? '[]', true)),
                'excludes' => implode("\n", json_decode($package->excluded ?? '[]', true)),
            ]);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn([
                'type', 'price_unit', 'location', 'badge', 'board', 'accommodation', 'period_label',
                'departures', 'highlights', 'itinerary', 'included', 'excluded',
            ]);
        });
    }

    /**
     * @return array<int, string>
     */
    private function lines(?string $text): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", (string) $text))));
    }
};
