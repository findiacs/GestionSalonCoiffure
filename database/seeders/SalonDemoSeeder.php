<?php

namespace Database\Seeders;

use App\Models\Avis;
use App\Models\Employe;
use App\Models\Reservation;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SalonDemoSeeder extends Seeder
{
    /**
     * Règle d'or : ce seeder n'écrit JAMAIS dans la colonne photo/image
     * d'un salon, d'un employé ou d'un service — sous aucun prétexte.
     *
     * - firstOrCreate sur Salon/Employe/Service : si la ligne existe,
     *   aucun champ n'est réécrasé (nom, adresse, prix, spécialités,
     *   horaires, photo… tout est conservé tel que personnalisé).
     * - Aucune photo de démo n'est attachée automatiquement : le rendu
     *   visuel des salons sans photo passe par le placeholder géré dans
     *   le modèle (Salon::getPhotoUrlAttribute), pas par le seeder.
     *
     * Pour bootstrapper des photos de démo sur une installation neuve,
     * passez par le dashboard salon (upload manuel).
     */
    public function run(): void
    {
        $rabatId = Ville::where('nom_ville', 'Rabat')->value('id');

        if (! $rabatId) {
            $this->command->error('VilleSeeder doit être exécuté avant SalonDemoSeeder.');

            return;
        }

        $horairesBase = json_encode([
            'lundi' => ['debut' => '09:00', 'fin' => '18:00', 'ferme' => false],
            'mardi' => ['debut' => '09:00', 'fin' => '18:00', 'ferme' => false],
            'mercredi' => ['debut' => '09:00', 'fin' => '18:00', 'ferme' => false],
            'jeudi' => ['debut' => '09:00', 'fin' => '18:00', 'ferme' => false],
            'vendredi' => ['debut' => '09:00', 'fin' => '18:00', 'ferme' => false],
            'samedi' => ['debut' => '10:00', 'fin' => '17:00', 'ferme' => false],
            'dimanche' => ['debut' => null,    'fin' => null,    'ferme' => true],
        ]);

        // ══════════════════════════════════════════════════════════
        // SALON 1 — Elegance Coiffure (Agdal)
        // ══════════════════════════════════════════════════════════
        $gerant1 = User::updateOrCreate(['email' => 'contact@elegance-rabat.ma'], [
            'prenom' => 'Elegance',
            'nom' => 'Coiffure',
            'mot_de_passe' => Hash::make('Salon@2026!'),
            'telephone' => '05371234567',
            'role' => 'salon',
            'email_verifie_le' => now(),
        ]);

        $salon1 = Salon::firstOrCreate(['user_id' => $gerant1->id], [
            'ville_id' => $rabatId,
            'nom_salon' => 'Elegance Coiffure',
            'adresse' => '12, Rue Ibn Sina',
            'quartier' => 'Agdal',
            'code_postal' => '10090',
            'telephone' => '05371234567',
            'email' => 'contact@elegance-rabat.ma',
            'horaires' => $horairesBase,
            'description' => 'Salon haut de gamme au cœur d\'Agdal. Équipe de professionnels qualifiés.',
            'note_moy' => 0,
            'nb_avis' => 0,
            'valide' => 1,
            'nb_employes' => 4,
            'latitude' => 33.9922000,
            'longitude' => -6.8545000,
            'date_valid' => now()->subMonths(6),
        ]);

        // Employés salon 1
        $emp1 = Employe::firstOrCreate(['email' => 'fatima.z@elegance.ma'], [
            'salon_id' => $salon1->id,
            'nom' => 'Zahra',
            'prenom' => 'Fatima',
            'tel' => '06611111111',
            'specialites' => json_encode(['Coupe femme', 'Coloration', 'Lissage', 'Brushing']),
            'horaires' => $horairesBase,
            'actif' => true,
        ]);

        $emp2 = Employe::firstOrCreate(['email' => 'sara.m@elegance.ma'], [
            'salon_id' => $salon1->id,
            'nom' => 'Moussaoui',
            'prenom' => 'Sara',
            'tel' => '06622222222',
            'specialites' => json_encode(['Soin visage', 'Épilation', 'Manucure']),
            'horaires' => $horairesBase,
            'actif' => true,
        ]);

        // Services salon 1
        $svc1 = Service::firstOrCreate(['salon_id' => $salon1->id, 'nom_service' => 'Coupe femme'], ['prix' => 120, 'duree_minu' => 45,  'categorie' => 'Coiffure', 'actif' => 1, 'description' => 'Coupe, shampooing et brushing inclus.']);
        $svc2 = Service::firstOrCreate(['salon_id' => $salon1->id, 'nom_service' => 'Coupe + Brushing'], ['prix' => 160, 'duree_minu' => 60,  'categorie' => 'Coiffure', 'actif' => 1, 'description' => 'Coupe sur mesure avec brushing volume longue durée.']);
        $svc3 = Service::firstOrCreate(['salon_id' => $salon1->id, 'nom_service' => 'Coloration globale'], ['prix' => 280, 'duree_minu' => 90,  'categorie' => 'Couleur',  'actif' => 1, 'description' => 'Coloration uniforme, produits inclus.']);
        $svc4 = Service::firstOrCreate(['salon_id' => $salon1->id, 'nom_service' => 'Mèches / Balayage'], ['prix' => 380, 'duree_minu' => 120, 'categorie' => 'Couleur',  'actif' => 1, 'description' => 'Balayage naturel. Brushing inclus.']);
        $svc5 = Service::firstOrCreate(['salon_id' => $salon1->id, 'nom_service' => 'Brushing seul'], ['prix' => 80,  'duree_minu' => 30,  'categorie' => 'Coiffure', 'actif' => 1, 'description' => 'Brushing volume et brillance.']);
        $svc6 = Service::firstOrCreate(['salon_id' => $salon1->id, 'nom_service' => 'Soin visage hydratant'], ['prix' => 190, 'duree_minu' => 60, 'categorie' => 'Soins',    'actif' => 1, 'description' => 'Nettoyage, gommage et masque hydratant.']);

        // ══════════════════════════════════════════════════════════
        // SALON 2 — Prestige Hair Studio (Hay Riad)
        // ══════════════════════════════════════════════════════════
        $gerant2 = User::updateOrCreate(['email' => 'prestige@gmail.com'], [
            'prenom' => 'Prestige',
            'nom' => 'Hair',
            'mot_de_passe' => Hash::make('Salon@2026!'),
            'telephone' => '05372345678',
            'role' => 'salon',
            'email_verifie_le' => now(),
        ]);

        $salon2 = Salon::firstOrCreate(['user_id' => $gerant2->id], [
            'ville_id' => $rabatId,
            'nom_salon' => 'Prestige Hair Studio',
            'adresse' => '45, Avenue Mohammed VI',
            'quartier' => 'Hay Riad',
            'code_postal' => '10100',
            'telephone' => '05372345678',
            'email' => 'prestige@gmail.com',
            'horaires' => $horairesBase,
            'description' => 'Studio spécialisé coloration et soins capillaires premium.',
            'note_moy' => 0,
            'nb_avis' => 0,
            'valide' => 1,
            'nb_employes' => 2,
            'latitude' => 33.9716000,
            'longitude' => -6.8498000,
            'date_valid' => now()->subMonths(5),
        ]);

        Employe::firstOrCreate(['email' => 'khadija@prestige.ma'], [
            'salon_id' => $salon2->id,
            'nom' => 'El Idrissi',
            'prenom' => 'Khadija',
            'tel' => '06655555555',
            'specialites' => json_encode(['Coupe femme', 'Coloration']),
            'horaires' => $horairesBase,
            'actif' => true,
        ]);

        Service::firstOrCreate(['salon_id' => $salon2->id, 'nom_service' => 'Coupe femme premium'], ['prix' => 150, 'duree_minu' => 60,  'categorie' => 'Coiffure', 'actif' => 1, 'description' => 'Consultation + coupe + brushing.']);
        Service::firstOrCreate(['salon_id' => $salon2->id, 'nom_service' => 'Lissage brésilien'], ['prix' => 550, 'duree_minu' => 180, 'categorie' => 'Couleur',  'actif' => 1, 'description' => 'Kératine longue durée. 4 à 6 mois.']);
        Service::firstOrCreate(['salon_id' => $salon2->id, 'nom_service' => 'Manucure classique'], ['prix' => 110, 'duree_minu' => 45,  'categorie' => 'Ongles',   'actif' => 1, 'description' => 'Soin mains + vernis.']);

        // ══════════════════════════════════════════════════════════
        // SALON 3 — L'Atelier Beauté (Souissi)
        // ══════════════════════════════════════════════════════════
        $gerant3 = User::updateOrCreate(['email' => 'atelier@gmail.com'], [
            'prenom' => 'Atelier',
            'nom' => 'Beaute',
            'mot_de_passe' => Hash::make('Salon@2026!'),
            'telephone' => '05373456789',
            'role' => 'salon',
            'email_verifie_le' => now(),
        ]);

        $salon3 = Salon::firstOrCreate(['user_id' => $gerant3->id], [
            'ville_id' => $rabatId,
            'nom_salon' => "L'Atelier Beauté",
            'adresse' => '8, Rue Patrice Lumumba',
            'quartier' => 'Souissi',
            'code_postal' => '10080',
            'telephone' => '05373456789',
            'email' => 'atelier@gmail.com',
            'horaires' => $horairesBase,
            'description' => 'Institut complet : coiffure, soins visage, onglerie, massage.',
            'note_moy' => 0,
            'nb_avis' => 0,
            'valide' => 1,
            'nb_employes' => 2,
            'latitude' => 33.9850000,
            'longitude' => -6.8620000,
            'date_valid' => now()->subMonths(8),
        ]);

        $emp3 = Employe::firstOrCreate(['email' => 'sara.mk@atelier.ma'], [
            'salon_id' => $salon3->id,
            'nom' => 'Moukrim',
            'prenom' => 'Sara',
            'tel' => '06688888888',
            'specialites' => json_encode(['Soin visage', 'Massage', 'Manucure', 'Pédicure']),
            'horaires' => $horairesBase,
            'actif' => true,
        ]);

        Service::firstOrCreate(['salon_id' => $salon3->id, 'nom_service' => 'Soin visage éclat'], ['prix' => 220, 'duree_minu' => 75, 'categorie' => 'Soins',   'actif' => 1, 'description' => 'Rituel : démaquillage, peeling, masque, sérum.']);
        Service::firstOrCreate(['salon_id' => $salon3->id, 'nom_service' => 'Massage relaxant'], ['prix' => 280, 'duree_minu' => 60, 'categorie' => 'Massage', 'actif' => 1, 'description' => 'Massage corps complet aux huiles essentielles.']);
        Service::firstOrCreate(['salon_id' => $salon3->id, 'nom_service' => 'Hammam & gommage'], ['prix' => 250, 'duree_minu' => 90, 'categorie' => 'Soins',   'actif' => 1, 'description' => 'Hammam traditionnel + savon beldi et kessa.']);

        // ══════════════════════════════════════════════════════════
        // RÉSERVATIONS DE DÉMO
        // ══════════════════════════════════════════════════════════
        $clientSalma = User::where('email', 'salma.benali@email.com')->first();
        $clientKarim = User::where('email', 'karim.mansouri@email.com')->first();

        if ($clientSalma && $svc2) {
            $resa1 = Reservation::create([
                'client_id' => $clientSalma->id,
                'salon_id' => $salon1->id,
                'service_id' => $svc2->id,
                'employe_id' => $emp1->id,
                'date_heure' => now()->addDays(2)->setTime(10, 0),
                'duree_minutes' => 60,
                'statut' => 'confirmee',
                'notes_client' => 'Pas trop court, merci.',
                'rappel_24h' => false,
                'rappel_2h' => false,
            ]);

            // Réservation terminée avec avis
            $resa2 = Reservation::create([
                'client_id' => $clientSalma->id,
                'salon_id' => $salon1->id,
                'service_id' => $svc1->id,
                'employe_id' => $emp1->id,
                'date_heure' => now()->subDays(10)->setTime(14, 30),
                'duree_minutes' => 45,
                'statut' => 'terminee',
                'rappel_24h' => true,
                'rappel_2h' => true,
            ]);

            Avis::create([
                'reservation_id' => $resa2->id,
                'note' => 5,
                'commentaire' => 'Excellente prestation ! Fatima sait exactement ce que je veux. Salon impeccable.',
                'reponse_salon' => 'Merci pour votre fidélité ! À très bientôt chez Elegance Coiffure.',
            ]);
        }

        if ($clientKarim && $svc3) {
            Reservation::create([
                'client_id' => $clientKarim->id,
                'salon_id' => $salon1->id,
                'service_id' => $svc3->id,
                'employe_id' => $emp2->id,
                'date_heure' => now()->addDays(5)->setTime(11, 0),
                'duree_minutes' => 90,
                'statut' => 'en_attente',
                'notes_client' => 'Couleur châtain clair.',
                'rappel_24h' => false,
                'rappel_2h' => false,
            ]);
        }

        $this->command->info('✓ SalonDemoSeeder : 3 salons, 4 employés, 12 services, 3 réservations, 1 avis.');
        $this->command->line('  → contact@elegance-rabat.ma / Salon@2026!');
    }
}
