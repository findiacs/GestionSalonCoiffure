<?php

namespace Tests\Unit\Services;

use App\Models\Employe;
use App\Models\Reservation;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Models\Ville;
use App\Services\GestionnaireDisponibilite;
use App\Services\NotificationService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use App\Mail\NouvelleReservationMail;

class ReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReservationService $service;
    private $dispoMock;
    private $notifMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispoMock = $this->createMock(GestionnaireDisponibilite::class);
        $this->notifMock = $this->createMock(NotificationService::class);

        $this->service = new ReservationService($this->dispoMock, $this->notifMock);

        Mail::fake();
    }

    protected function refreshDatabase()
    {
        if (! isset($this->app)) {
            return;
        }

        try {
            $this->artisan('migrate:fresh');
        } catch(\Exception $e) {
            if (!str_contains($e->getMessage(), 'MODIFY COLUMN')) {
                throw $e;
            }
        }

        $this->app[ \Illuminate\Contracts\Console\Kernel::class]->setArtisan(null);
    }

    public function test_creer_reservation_succes()
    {
        $client = User::factory()->create(['role' => 'client']);
        $salonOwner = User::factory()->create(['role' => 'salon']);
        $ville = Ville::factory()->create();

        $salon = Salon::factory()->create(['user_id' => $salonOwner->id, 'ville_id' => $ville->id]);
        $serviceModel = Service::factory()->create(['salon_id' => $salon->id, 'duree_minu' => 45]);
        $employe = Employe::factory()->create(['salon_id' => $salon->id]);

        $dateHeure = Carbon::now()->addDays(1)->setHour(10)->setMinute(0)->format('Y-m-d H:i:s');
        $data = [
            'service_id' => $serviceModel->id,
            'employe_id' => $employe->id,
            'date_heure' => $dateHeure,
            'notes_client' => 'Merci de préparer le café',
        ];

        $this->dispoMock->expects($this->once())
             ->method('estDisponible')
             ->willReturn(true);

        $this->notifMock->expects($this->once())
             ->method('envoyer')
             ->with(
                 $salonOwner->id,
                 'nouvelle_reservation',
                 $this->callback(function($params) {
                     return isset($params['client']) && isset($params['service']);
                 })
             );

        $reservation = $this->service->creer($client, $salon, $data);

        $this->assertInstanceOf(Reservation::class, $reservation);
        $this->assertEquals($client->id, $reservation->client_id);
        $this->assertEquals($salon->id, $reservation->salon_id);
        $this->assertEquals($serviceModel->id, $reservation->service_id);
        $this->assertEquals($employe->id, $reservation->employe_id);
        $this->assertEquals('en_attente', $reservation->statut);
        $this->assertEquals(45, $reservation->duree_minutes);
        $this->assertEquals('Merci de préparer le café', $reservation->notes_client);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'client_id' => $client->id,
            'statut' => 'en_attente'
        ]);

        Mail::assertSent(NouvelleReservationMail::class, function ($mail) use ($salonOwner) {
            return $mail->hasTo($salonOwner->email);
        });
    }

    public function test_creer_reservation_indisponible()
    {
        $client = User::factory()->create(['role' => 'client']);
        $salonOwner = User::factory()->create(['role' => 'salon']);
        $ville = Ville::factory()->create();

        $salon = Salon::factory()->create(['user_id' => $salonOwner->id, 'ville_id' => $ville->id]);
        $serviceModel = Service::factory()->create(['salon_id' => $salon->id]);

        $dateHeure = Carbon::now()->addDays(1)->format('Y-m-d H:i:s');
        $data = [
            'service_id' => $serviceModel->id,
            'date_heure' => $dateHeure,
        ];

        $this->dispoMock->expects($this->once())
             ->method('estDisponible')
             ->willReturn(false);

        try {
            $this->service->creer($client, $salon, $data);
            $this->fail('Expected an abort(409) exception.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(409, $e->getStatusCode());
            $this->assertStringContainsString('Ce créneau n\'est plus disponible', $e->getMessage());
        }

        $this->assertDatabaseCount('reservations', 0);
    }
}
