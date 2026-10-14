<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! User::where('role', UserRole::Admin)->exists()) {
            User::factory()->admin()->create([
                'name' => 'Amministratore',
                'email' => 'admin@tourflow.test',
            ]);
        }

        Setting::setMany([
            'company_name' => 'TourFlow Viaggi',
            'whatsapp_number' => '393331234567',
            'contact_email' => 'info@tourflow.test',
            'contact_phone' => '+39 06 1234567',
            'address' => 'Via Roma 1, 00100 Roma (RM)',
            'vat_number' => '01234567890',
        ]);

        $this->call([
            DemoAgencySeeder::class,
            DemoCatalogSeeder::class,
        ]);
    }
}
