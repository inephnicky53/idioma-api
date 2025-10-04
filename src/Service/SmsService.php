<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SmsService
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public const SMS_FROM = "IDIOMA";
    private const BASE_URL = 'https://www.unikron.tech/api/v2/sms/send';

    private function getAppKey(): string
    {
        $key = $_ENV['UNIKRON_APP_KEY'] ?? $_SERVER['UNIKRON_APP_KEY'] ?? null;
        if (!$key) {
            throw new \RuntimeException('UNIKRON_APP_KEY is not configured. Add it to your environment.');
        }
        return $key;
    }

    /**
     * Envoie un ou plusieurs SMS via l'API v2.
     *
     * @param string|array $number Numéro unique ou tableau de numéros
     * @param string $text Contenu du SMS
     * @param string|null $sender Nom de l'expéditeur (max 11 caractères)
     * @return array Réponse décodée de l'API
     */
    public function send(string|array $number, string $text, ?string $sender = null): array
    {
        $numbers = is_array($number) ? implode(',', array_map('trim', $number)) : trim($number);
        $from = substr($sender ?? self::SMS_FROM, 0, 11);

        $payload = [
            'number' => $numbers,
            'text' => $text,
            'sender' => $from,
        ];

        $response = $this->httpClient->request('POST', self::BASE_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAppKey(),
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        $status = $response->getStatusCode();
        $body = $response->getContent(false);
        $data = json_decode($body, true);

        // Gestion des codes d'erreur documentés
        if ($status === 401) {
            throw new \RuntimeException('AppKey invalide ou manquante (401).');
        }
        if ($status === 400) {
            throw new \InvalidArgumentException('Paramètres manquants ou invalides (400).');
        }
        if ($status === 422) {
            throw new \RuntimeException('Trop de numéros dans la requête (422).');
        }
        if ($status >= 400) {
            throw new \RuntimeException('Erreur API SMS v2: HTTP ' . $status . ' ' . $body);
        }

        return is_array($data) ? $data : ['status' => 'processed', 'message' => 'Request processed', 'raw' => $body];
    }

    /**
     * Compatibilité ancienne méthode: envoie un SMS avec la nouvelle API.
     */
    public function sendBc($phone, $message): array
    {
        return $this->send($phone, $message, self::SMS_FROM);
    }
}