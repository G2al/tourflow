<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Ore di anticipo dei promemoria di scadenza già inviati (es. [24, 1]).
            $table->json('option_reminders_sent')->nullable()->after('option_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('option_reminders_sent');
        });
    }
};
