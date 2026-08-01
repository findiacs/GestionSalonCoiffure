<?php

namespace Tests\Unit;

use App\Mail\Rappel24hMail;
use App\Models\Reservation;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Models\Ville;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = new NotificationService;
        Mail::fake();
    }

    public function test_envoyer_rappels_24h_happy_path()
    {
        // Setup data
        $ville = Ville::create([
            'nom_ville' => 'Casablanca',
            'code_postal' => '20000',
            'region' => 'Casablanca-Settat',
            'pays' => 'Maroc',
            'actif' => true,
        ]);

        $client = User::create([
            'nom' => 'Doe',
            'prenom' => 'John',
            'email' => 'john@example.com',
            'mot_de_passe' => bcrypt('password'),
            'role' => 'client',
            'ville_id' => $ville->id,
        ]);

        $salonUser = User::create([
            'nom' => 'SalonOwner',
            'prenom' => 'Jane',
            'email' => 'jane@example.com',
            'mot_de_passe' => bcrypt('password'),
            'role' => 'salon',
            'ville_id' => $ville->id,
        ]);

        $salon = Salon::create([
            'user_id' => $salonUser->id,
            'ville_id' => $ville->id,
            'nom_salon' => 'Mon Super Salon',
            'adresse' => '123 Rue de la Coiffure',
        ]);

        $service = Service::create([
            'salon_id' => $salon->id,
            'nom_service' => 'Coupe Homme',
            'prix' => 100.00,
        ]);

        $reservation = Reservation::create([
            'client_id' => $client->id,
            'salon_id' => $salon->id,
            'service_id' => $service->id,
            'date_heure' => now()->addHours(24),
            'statut' => 'confirmee',
            'rappel_24h' => false,
        ]);

        // Execute
        $count = $this->notificationService->envoyerRappels24h();

        // Assert
        $this->assertEquals(1, $count);
        $this->assertTrue($reservation->fresh()->rappel_24h);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'rappel_24h',
        ]);

        Mail::assertSent(Rappel24hMail::class, function ($mail) use ($client) {
            return $mail->hasTo($client->email);
        });
    }

    public function test_envoyer_rappels_24h_ignores_out_of_time_window()
    {
        // Setup data
        $ville = Ville::create([
            'nom_ville' => 'Casablanca',
            'code_postal' => '20000',
            'region' => 'Casablanca-Settat',
            'pays' => 'Maroc',
            'actif' => true,
        ]);

        $client = User::create([
            'nom' => 'Doe',
            'prenom' => 'John',
            'email' => 'john2@example.com',
            'mot_de_passe' => bcrypt('password'),
            'role' => 'client',
            'ville_id' => $ville->id,
        ]);

        $salonUser = User::create([
            'nom' => 'SalonOwner',
            'prenom' => 'Jane',
            'email' => 'jane2@example.com',
            'mot_de_passe' => bcrypt('password'),
            'role' => 'salon',
            'ville_id' => $ville->id,
        ]);

        $salon = Salon::create([
            'user_id' => $salonUser->id,
            'ville_id' => $ville->id,
            'nom_salon' => 'Mon Super Salon',
            'adresse' => '123 Rue de la Coiffure',
        ]);

        $service = Service::create([
            'salon_id' => $salon->id,
            'nom_service' => 'Coupe Homme',
            'prix' => 100.00,
        ]);

        // Reservation in 10 hours (too soon)
        Reservation::create([
            'client_id' => $client->id,
            'salon_id' => $salon->id,
            'service_id' => $service->id,
            'date_heure' => now()->addHours(10),
            'statut' => 'confirmee',
            'rappel_24h' => false,
        ]);

        // Reservation in 48 hours (too late)
        Reservation::create([
            'client_id' => $client->id,
            'salon_id' => $salon->id,
            'service_id' => $service->id,
            'date_heure' => now()->addHours(48),
            'statut' => 'confirmee',
            'rappel_24h' => false,
        ]);

        // Execute
        $count = $this->notificationService->envoyerRappels24h();

        // Assert
        $this->assertEquals(0, $count);
        Mail::assertNothingSent();
    }

    public function test_envoyer_rappels_24h_ignores_wrong_status()
    {
        // Setup data
        $ville = Ville::create([
            'nom_ville' => 'Casablanca',
            'code_postal' => '20000',
            'region' => 'Casablanca-Settat',
            'pays' => 'Maroc',
            'actif' => true,
        ]);

        $client = User::create([
            'nom' => 'Doe',
            'prenom' => 'John',
            'email' => 'john3@example.com',
            'mot_de_passe' => bcrypt('password'),
            'role' => 'client',
            'ville_id' => $ville->id,
        ]);

        $salonUser = User::create([
            'nom' => 'SalonOwner',
            'prenom' => 'Jane',
            'email' => 'jane3@example.com',
            'mot_de_passe' => bcrypt('password'),
            'role' => 'salon',
            'ville_id' => $ville->id,
        ]);

        $salon = Salon::create([
            'user_id' => $salonUser->id,
            'ville_id' => $ville->id,
            'nom_salon' => 'Mon Super Salon',
            'adresse' => '123 Rue de la Coiffure',
        ]);

        $service = Service::create([
            'salon_id' => $salon->id,
            'nom_service' => 'Coupe Homme',
            'prix' => 100.00,
        ]);

        // Reservation en_attente
        Reservation::create([
            'client_id' => $client->id,
            'salon_id' => $salon->id,
            'service_id' => $service->id,
            'date_heure' => now()->addHours(24),
            'statut' => 'en_attente',
            'rappel_24h' => false,
        ]);

        // Execute
        $count = $this->notificationService->envoyerRappels24h();

        // Assert
        $this->assertEquals(0, $count);
        Mail::assertNothingSent();
    }

    public function test_envoyer_rappels_24h_ignores_already_reminded()
    {
        // Setup data
        $ville = Ville::create([
            'nom_ville' => 'Casablanca',
            'code_postal' => '20000',
            'region' => 'Casablanca-Settat',
            'pays' => 'Maroc',
            'actif' => true,
        ]);

        $client = User::create([
            'nom' => 'Doe',
            'prenom' => 'John',
            'email' => 'john4@example.com',
            'mot_de_passe' => bcrypt('password'),
            'role' => 'client',
            'ville_id' => $ville->id,
        ]);

        $salonUser = User::create([
            'nom' => 'SalonOwner',
            'prenom' => 'Jane',
            'email' => 'jane4@example.com',
            'mot_de_passe' => bcrypt('password'),
            'role' => 'salon',
            'ville_id' => $ville->id,
        ]);

        $salon = Salon::create([
            'user_id' => $salonUser->id,
            'ville_id' => $ville->id,
            'nom_salon' => 'Mon Super Salon',
            'adresse' => '123 Rue de la Coiffure',
        ]);

        $service = Service::create([
            'salon_id' => $salon->id,
            'nom_service' => 'Coupe Homme',
            'prix' => 100.00,
        ]);

        // Reservation already reminded
        Reservation::create([
            'client_id' => $client->id,
            'salon_id' => $salon->id,
            'service_id' => $service->id,
            'date_heure' => now()->addHours(24),
            'statut' => 'confirmee',
            'rappel_24h' => true,
        ]);

        // Execute
        $count = $this->notificationService->envoyerRappels24h();

        // Assert
        $this->assertEquals(0, $count);
        Mail::assertNothingSent();
    }
}
