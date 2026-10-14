<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('vat_number', 20)->unique();
            $table->string('tax_code', 20)->nullable();
            $table->string('address');
            $table->string('city');
            $table->string('zip_code', 10);
            $table->string('province', 5)->nullable();
            $table->string('country', 2)->default('IT');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('pec')->nullable();
            $table->string('sdi_code', 10)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('license_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agencies');
    }
};
