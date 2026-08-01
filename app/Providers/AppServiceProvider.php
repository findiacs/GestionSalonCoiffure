<?php

namespace App\Providers;

use App\Mail\Transport\BrevoTransport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        Carbon::setLocale('fr');
        setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'fra');

        Mail::extend('brevo', function () {
            $apiKey = (string) config('mail.mailers.brevo.api_key');

            Log::info('[Boot] Enregistrement transport Brevo', [
                'mailer_default' => config('mail.default'),
                'has_api_key' => $apiKey !== '',
                'api_key_prefix' => $apiKey !== '' ? substr($apiKey, 0, 12).'...' : null,
            ]);

            return new BrevoTransport($apiKey);
        });
    }
}
