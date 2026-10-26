<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le prenotazioni diventano "pratiche": più prodotti (soggiorni, volo + hotel,
 * escursioni, transfer), fino a 4 camere per soggiorno, passeggeri condivisi,
 * assicurazioni per pratica, opzione a tempo e documenti (preventivo, proforma).
 *
 * Le vecchie tabelle (una prenotazione = un pacchetto) vengono sostituite:
 * contenevano solo richieste di prova.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('booking_extras');
        Schema::dropIfExists('booking_participants');
        Schema::dropIfExists('bookings');

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('access_token', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->index();

            $table->string('contact_first_name');
            $table->string('contact_last_name');
            $table->string('contact_email');
            $table->string('contact_phone');
            $table->string('agency_reference')->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();

            // Importi (calcolati dal server)
            $table->decimal('items_total', 10, 2);
            $table->decimal('insurances_total', 10, 2)->default(0);
            $table->boolean('price_lock')->default(false);
            $table->decimal('price_lock_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->decimal('fee_percent', 5, 2)->nullable();
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->decimal('amount_due', 10, 2);
            $table->decimal('deposit_percent', 5, 2);
            $table->decimal('deposit_amount', 10, 2);
            $table->json('price_breakdown');

            // Ciclo di vita
            $table->date('quote_valid_until')->nullable();
            $table->timestamp('option_expires_at')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('proforma_sent_at')->nullable();
            $table->timestamp('proforma_viewed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Accettazione termini
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_accepted_ip', 45)->nullable();
            $table->json('accepted_legal_pages')->nullable();

            $table->timestamps();
        });

        Schema::create('booking_passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->boolean('is_lead')->default(false);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->date('birth_date');
            $table->string('gender', 1)->nullable();
            $table->string('tax_code', 20)->nullable();
            $table->string('nationality', 50)->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_number')->nullable();
            $table->date('document_expiry')->nullable();
            $table->string('age_category', 10);
            $table->timestamps();

            $table->unique(['booking_id', 'position']);
        });

        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->string('mode', 20);
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('flight_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('nights')->default(0);
            $table->unsignedSmallInteger('seats')->default(0);
            $table->json('passenger_positions');
            $table->decimal('total', 10, 2);
            $table->json('breakdown');
            $table->timestamps();
        });

        Schema::create('booking_item_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->date('check_in');
            $table->date('check_out');
            $table->json('passenger_positions');
            $table->decimal('total', 10, 2);
            $table->timestamps();

            $table->index(['room_type_id', 'check_in', 'check_out']);
        });

        Schema::create('booking_insurances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('unit_price', 10, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('total', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_insurances');
        Schema::dropIfExists('booking_item_rooms');
        Schema::dropIfExists('booking_items');
        Schema::dropIfExists('booking_passengers');
        Schema::dropIfExists('bookings');
    }
};
