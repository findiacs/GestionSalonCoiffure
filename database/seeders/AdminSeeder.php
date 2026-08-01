<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // ── Compte administrateur principal ──────────────────────
        $admin = User::updateOrCreate(
            ['email' => 'admin@salonify.ma'],
            [
                'prenom' => 'Admin',
                'nom' => 'Salonify',
                'mot_de_passe' => Hash::make('Admin@2026!'),
                'telephone' => '05370000001',
                'role' => 'admin',
                'email_verifie_le' => now(),
            ]
        );

        // ── Clients de test ───────────────────────────────────────
        $clients = [
            ['prenom' => 'Salma',  'nom' => 'Benali',   'email' => 'salma.benali@email.com',   'tel' => '06612345678'],
            ['prenom' => 'Karim',  'nom' => 'Mansouri', 'email' => 'karim.mansouri@email.com', 'tel' => '06623456789'],
            ['prenom' => 'Imane',  'nom' => 'Tazi',     'email' => 'imane.tazi@email.com',     'tel' => '06634567890'],
            ['prenom' => 'Yousra', 'nom' => 'Alaoui',   'email' => 'yousra.alaoui@email.com',  'tel' => '06645678901'],
            ['prenom' => 'Nadia',  'nom' => 'Hakimi',   'email' => 'nadia.hakimi@email.com',   'tel' => '06656789012'],
        ];

        foreach ($clients as $c) {
            User::updateOrCreate(
                ['email' => $c['email']],
                [
                    'prenom' => $c['prenom'],
                    'nom' => $c['nom'],
                    'mot_de_passe' => Hash::make('Client@2026!'),
                    'telephone' => $c['tel'],
                    'role' => 'client',
                    'email_verifie_le' => now(),
                ]
            );
        }

        $this->command->info('✓ AdminSeeder : 1 admin + '.count($clients).' clients créés.');
        $this->command->line('  → admin@salonify.ma / Admin@2026!');
        $this->command->line('  → salma.benali@email.com / Client@2026!');
    }
}
