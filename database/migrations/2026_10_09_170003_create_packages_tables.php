<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained()->restrictOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->json('gallery')->nullable();
            $table->unsignedSmallInteger('nights');
            $table->string('board_type', 5);
            $table->date('valid_from');
            $table->date('valid_to');

            // Volo
            $table->boolean('flight_included')->default(true);
            $table->string('departure_airport')->nullable();
            $table->decimal('flight_price_gross', 10, 2)->nullable();
            $table->decimal('flight_price_net', 10, 2)->nullable();

            // Trasferimenti
            $table->boolean('transfer_included')->default(true);
            $table->decimal('transfer_price_gross', 10, 2)->nullable();
            $table->decimal('transfer_price_net', 10, 2)->nullable();

            // Quote accessorie per persona
            $table->decimal('registration_fee_gross', 10, 2)->default(0);
            $table->decimal('registration_fee_net', 10, 2)->default(0);
            $table->decimal('airport_taxes', 10, 2)->default(0);

            // Fasce d'età (età compiuta alla data di partenza)
            $table->unsignedTinyInteger('infant_max_age')->default(1);
            $table->unsignedTinyInteger('child_max_age')->default(11);

            $table->text('includes')->nullable();
            $table->text('excludes')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->text('notes')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'valid_from', 'valid_to']);
        });

        Schema::create('package_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->date('start_date');
            $table->date('end_date');

            // Quota base: adulto in camera doppia
            $table->decimal('adult_price_gross', 10, 2);
            $table->decimal('adult_price_net', 10, 2);
            // 3°/4° letto adulto
            $table->decimal('extra_bed_adult_price_gross', 10, 2)->nullable();
            $table->decimal('extra_bed_adult_price_net', 10, 2)->nullable();
            $table->decimal('child_price_gross', 10, 2)->nullable();
            $table->decimal('child_price_net', 10, 2)->nullable();
            $table->decimal('infant_price_gross', 10, 2)->nullable();
            $table->decimal('infant_price_net', 10, 2)->nullable();
            $table->decimal('single_supplement_gross', 10, 2)->nullable();
            $table->decimal('single_supplement_net', 10, 2)->nullable();

            $table->timestamps();

            $table->index(['package_id', 'start_date', 'end_date']);
        });

        Schema::create('excursion_package', function (Blueprint $table) {
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('excursion_id')->constrained()->cascadeOnDelete();
            $table->primary(['package_id', 'excursion_id']);
        });

        Schema::create('insurance_package', function (Blueprint $table) {
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_id')->constrained()->cascadeOnDelete();
            $table->primary(['package_id', 'insurance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_package');
        Schema::dropIfExists('excursion_package');
        Schema::dropIfExists('package_periods');
        Schema::dropIfExists('packages');
    }
};
