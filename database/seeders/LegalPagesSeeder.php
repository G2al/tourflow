<?php

namespace Database\Seeders;

use App\Models\LegalPage;
use Illuminate\Database\Seeder;

/**
 * Testi legali di esempio: vanno sostituiti dal pannello con quelli definitivi.
 */
class LegalPagesSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            ['title' => 'Condizioni generali di vendita', 'slug' => 'condizioni-di-vendita'],
            ['title' => 'Termini e condizioni', 'slug' => 'termini-e-condizioni'],
            ['title' => 'Privacy policy', 'slug' => 'privacy-policy'],
        ];

        foreach ($pages as $order => $page) {
            LegalPage::firstOrCreate(['slug' => $page['slug']], [
                'title' => $page['title'],
                'content' => "<p><strong>Testo provvisorio.</strong> Sostituire dal pannello (Testi legali) con il testo definitivo di \"{$page['title']}\".</p>",
                'requires_acceptance' => true,
                'sort_order' => $order,
            ]);
        }
    }
}
