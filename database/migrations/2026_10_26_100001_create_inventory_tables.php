<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventario acquistato dal tour operator:
 * - tipologie di camera per hotel, con supplemento a persona a notte;
 * - allotment: quante camere di una tipologia sono disponibili OGNI NOTTE di un periodo;
 * - voli A/R a date fisse, con posti e prezzi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('max_occupancy')->default(4);
            $table->decimal('supplement', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('room_allotments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('rooms');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['room_type_id', 'start_date', 'end_date']);
        });

        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained()->cascadeOnDelete();
            $table->string('departure_airport', 3)->index();
            $table->date('outbound_date');
            $table->date('return_date');
            $table->string('carrier')->nullable();
            $table->string('outbound_details')->nullable();
            $table->string('return_details')->nullable();
            $table->unsignedSmallInteger('seats');
            $table->decimal('adult_price', 10, 2);
            $table->decimal('child_price', 10, 2)->nullable();
            $table->decimal('infant_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['destination_id', 'outbound_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flights');
        Schema::dropIfExists('room_allotments');
        Schema::dropIfExists('room_types');
    }
};
