<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

class BrevoTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(private string $apiKey)
    {
        parent::__construct();

        if (empty($this->apiKey)) {
            Log::error('[Brevo] ⚠ API key vide — BREVO_API_KEY non défini dans l\'environnement Railway');
        } else {
            Log::info('[Brevo] Transport initialisé', [
                'api_key_prefix' => substr($this->apiKey, 0, 12).'...',
                'api_key_length' => strlen($this->apiKey),
            ]);
        }
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $fromList = $email->getFrom();
        if (empty($fromList)) {
            Log::error('[Brevo] ✗ Pas d\'expéditeur (From) défini sur le message');
            throw new \RuntimeException('Brevo: expéditeur manquant (MAIL_FROM_ADDRESS).');
        }
        $from = $fromList[0];

        $toAddresses = array_map(fn ($a) => array_filter([
            'email' => $a->getAddress(),
            'name' => $a->getName() ?: null,
        ]), $email->getTo());

        $ccAddresses = array_map(fn ($a) => array_filter([
            'email' => $a->getAddress(),
            'name' => $a->getName() ?: null,
        ]), $email->getCc());

        $replyToList = array_map(fn ($a) => array_filter([
            'email' => $a->getAddress(),
            'name' => $a->getName() ?: null,
        ]), $email->getReplyTo());

        $payload = array_filter([
            'sender' => array_filter([
                'email' => $from->getAddress(),
                'name' => $from->getName() ?: null,
            ]),
            'to' => array_values($toAddresses),
            'cc' => ! empty($ccAddresses) ? array_values($ccAddresses) : null,
            'replyTo' => ! empty($replyToList) ? ($replyToList[0] ?? null) : null,
            'subject' => $email->getSubject(),
            'htmlContent' => $email->getHtmlBody(),
            'textContent' => $email->getTextBody(),
        ]);

        $recipients = array_map(fn ($a) => $a->getAddress(), $email->getTo());
        $subject = $email->getSubject();

        Log::info('[Brevo] → Envoi email', [
            'from' => $from->getAddress(),
            'to' => $recipients,
            'subject' => $subject,
            'has_html' => ! empty($email->getHtmlBody()),
            'has_text' => ! empty($email->getTextBody()),
        ]);

        if (empty($this->apiKey)) {
            throw new \RuntimeException(
                'Brevo: BREVO_API_KEY est vide. Définissez-la dans les variables Railway.'
            );
        }

        try {
            $response = Http::withHeaders([
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(20)
                ->connectTimeout(10)
                ->post(self::ENDPOINT, $payload);
        } catch (\Throwable $e) {
            Log::error('[Brevo] ✗ Exception réseau', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'to' => $recipients,
                'subject' => $subject,
            ]);
            throw new \RuntimeException(
                'Brevo API erreur réseau : '.$e->getMessage(), 0, $e
            );
        }

        if (! $response->successful()) {
            Log::error('[Brevo] ✗ Réponse API non-OK', [
                'status' => $response->status(),
                'body' => $response->body(),
                'to' => $recipients,
                'subject' => $subject,
            ]);
            throw new \RuntimeException(
                'Brevo API error (HTTP '.$response->status().'): '.$response->body()
            );
        }

        Log::info('[Brevo] ✓ Email envoyé', [
            'to' => $recipients,
            'subject' => $subject,
            'message_id' => $response->json('messageId'),
            'status' => $response->status(),
        ]);
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}
