<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->foreignId('package_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending')->index();

            $table->date('departure_date');
            $table->date('return_date');

            // Referente della prenotazione
            $table->string('contact_first_name');
            $table->string('contact_last_name');
            $table->string('contact_email');
            $table->string('contact_phone');
            $table->string('agency_reference')->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();

            // Prezzi calcolati lato server al momento della richiesta
            $table->string('price_type', 10); // gross | net
            $table->decimal('total_amount', 10, 2);
            $table->decimal('total_gross', 10, 2);
            $table->json('price_breakdown')->nullable();

            $table->timestamps();
        });

        Schema::create('booking_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_lead')->default(false);
            $table->string('first_name');
            $table->string('last_name');
            $table->date('birth_date');
            $table->string('gender', 1);
            $table->string('tax_code', 20)->nullable();
            $table->string('nationality', 50)->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_number')->nullable();
            $table->date('document_expiry')->nullable();
            $table->string('age_category', 10);
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('booking_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // excursion | insurance
            $table->nullableMorphs('extraable');
            $table->string('name');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('booking_extras');
        Schema::dropIfExists('booking_participants');
        Schema::dropIfExists('bookings');
    }
};
