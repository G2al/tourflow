<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fondamenta del motore di prenotazione:
 * - un solo prezzo (al pubblico): il netto agenzia diventa una fee % per agenzia;
 * - fasce d'età globali (impostazioni) al posto di quelle per pacchetto;
 * - soggiorni prenotabili come "solo soggiorno" e/o "volo + hotel";
 * - via le escursioni "extra" (ora sono prodotti del carrello) e il legame
 *   assicurazioni-pacchetto (le assicurazioni valgono per tutta la pratica);
 * - testi legali gestiti dal pannello.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('excursion_package');
        Schema::dropIfExists('excursions');
        Schema::dropIfExists('insurance_package');

        Schema::table('agencies', function (Blueprint $table) {
            $table->decimal('fee_percent', 5, 2)->nullable()->after('license_number');
        });

        Schema::table('insurances', fn (Blueprint $table) => $table->dropColumn('price_net'));
        Schema::table('insurances', fn (Blueprint $table) => $table->renameColumn('price_gross', 'price'));

        Schema::table('package_periods', fn (Blueprint $table) => $table->dropColumn([
            'adult_price_net', 'extra_bed_adult_price_net', 'child_price_net', 'infant_price_net', 'single_supplement_net',
        ]));
        Schema::table('package_periods', function (Blueprint $table) {
            $table->renameColumn('adult_price_gross', 'adult_price');
            $table->renameColumn('extra_bed_adult_price_gross', 'extra_bed_adult_price');
            $table->renameColumn('child_price_gross', 'child_price');
            $table->renameColumn('infant_price_gross', 'infant_price');
            $table->renameColumn('single_supplement_gross', 'single_supplement');
        });

        Schema::table('packages', fn (Blueprint $table) => $table->dropColumn([
            'transfer_price_net', 'registration_fee_net', 'infant_max_age', 'child_max_age',
        ]));
        Schema::table('packages', function (Blueprint $table) {
            $table->renameColumn('transfer_price_gross', 'transfer_price');
            $table->renameColumn('registration_fee_gross', 'registration_fee');
        });
        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('hotel_only_enabled')->default(true)->after('nights');
            $table->boolean('flight_hotel_enabled')->default(false)->after('hotel_only_enabled');
            $table->unsignedSmallInteger('min_nights')->default(1)->after('flight_hotel_enabled');
        });

        Schema::create('legal_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->boolean('requires_acceptance')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_pages');

        Schema::table('packages', fn (Blueprint $table) => $table->dropColumn(['hotel_only_enabled', 'flight_hotel_enabled', 'min_nights']));
        Schema::table('packages', function (Blueprint $table) {
            $table->renameColumn('transfer_price', 'transfer_price_gross');
            $table->renameColumn('registration_fee', 'registration_fee_gross');
        });
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('transfer_price_net', 10, 2)->nullable();
            $table->decimal('registration_fee_net', 10, 2)->default(0);
            $table->unsignedTinyInteger('infant_max_age')->default(1);
            $table->unsignedTinyInteger('child_max_age')->default(11);
        });

        Schema::table('package_periods', function (Blueprint $table) {
            $table->renameColumn('adult_price', 'adult_price_gross');
            $table->renameColumn('extra_bed_adult_price', 'extra_bed_adult_price_gross');
            $table->renameColumn('child_price', 'child_price_gross');
            $table->renameColumn('infant_price', 'infant_price_gross');
            $table->renameColumn('single_supplement', 'single_supplement_gross');
        });
        Schema::table('package_periods', function (Blueprint $table) {
            $table->decimal('adult_price_net', 10, 2)->default(0);
            $table->decimal('extra_bed_adult_price_net', 10, 2)->nullable();
            $table->decimal('child_price_net', 10, 2)->nullable();
            $table->decimal('infant_price_net', 10, 2)->nullable();
            $table->decimal('single_supplement_net', 10, 2)->nullable();
        });

        Schema::table('insurances', fn (Blueprint $table) => $table->renameColumn('price', 'price_gross'));
        Schema::table('insurances', fn (Blueprint $table) => $table->decimal('price_net', 10, 2)->default(0));

        Schema::table('agencies', fn (Blueprint $table) => $table->dropColumn('fee_percent'));

        Schema::create('insurance_package', function (Blueprint $table) {
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_id')->constrained()->cascadeOnDelete();
            $table->primary(['package_id', 'insurance_id']);
        });
        Schema::create('excursions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('duration')->nullable();
            $table->string('image')->nullable();
            $table->decimal('adult_price_gross', 10, 2)->default(0);
            $table->decimal('adult_price_net', 10, 2)->default(0);
            $table->decimal('child_price_gross', 10, 2)->nullable();
            $table->decimal('child_price_net', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('excursion_package', function (Blueprint $table) {
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('excursion_id')->constrained()->cascadeOnDelete();
            $table->primary(['package_id', 'excursion_id']);
        });
    }
};
