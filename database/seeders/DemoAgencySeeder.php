<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Agenzia di prova (agenzia@tourflow.test / password). Solo per sviluppo e
 * test: non va caricata in produzione.
 */
class DemoAgencySeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(['email' => 'agenzia@tourflow.test'], [
            'name' => 'Mario Rossi',
            'phone' => '+39 333 1111111',
            'password' => 'password',
            'role' => UserRole::Agency,
        ]);

        $user->agency()->firstOrCreate([], [
            'company_name' => 'Viaggi Sole Srl',
            'vat_number' => '09876543210',
            'tax_code' => '09876543210',
            'address' => 'Corso Italia 25',
            'city' => 'Milano',
            'zip_code' => '20122',
            'province' => 'MI',
            'phone' => '+39 02 9876543',
            'email' => 'info@viaggisole.test',
            'pec' => 'viaggisole@pec.test',
            'sdi_code' => 'M5UXCR1',
            'contact_person' => 'Mario Rossi',
            'license_number' => 'AUT-MI-2019-123',
        ]);
    }
}
