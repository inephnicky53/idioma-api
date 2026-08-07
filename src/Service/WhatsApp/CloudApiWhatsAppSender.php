<?php

namespace App\Service\WhatsApp;

use App\Contract\WhatsAppSenderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Envoi WhatsApp via la Cloud API officielle de Meta (graph.facebook.com).
 *
 * Aucune méthode ne lève d'exception : une notification qui échoue ne doit
 * jamais faire tomber le flux métier qui l'a déclenchée (inscription, paiement).
 * Les échecs sont tracés et remontés via la valeur de retour.
 */
readonly class CloudApiWhatsAppSender implements WhatsAppSenderInterface
{
    /** Indicatif appliqué aux numéros saisis au format national (RDC). */
    private const DEFAULT_COUNTRY_CODE = '243';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface     $logger,
        private ?string             $accessToken = null,
        private ?string             $phoneNumberId = null,
        private string              $apiVersion = 'v21.0',
        private string              $defaultLanguage = 'fr',
    ) {}

    public function isConfigured(): bool
    {
        return !empty($this->accessToken) && !empty($this->phoneNumberId);
    }

    public function sendTemplate(
        string $to,
        string $templateName,
        array $bodyParameters = [],
        array $extraComponents = [],
    ): bool {
        if (!$this->isConfigured()) {
            $this->logger->warning('WhatsApp non configuré : message ignoré', [
                'template' => $templateName,
            ]);

            return false;
        }

        $recipient = $this->normalizeNumber($to);
        if ($recipient === null) {
            $this->logger->warning('WhatsApp : numéro de destinataire inexploitable', [
                'template' => $templateName,
            ]);

            return false;
        }

        $components = [];

        if ($bodyParameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(
                    static fn (string $value): array => ['type' => 'text', 'text' => $value],
                    array_values($bodyParameters)
                ),
            ];
        }

        foreach ($extraComponents as $component) {
            $components[] = $component;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipient,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $this->defaultLanguage],
            ],
        ];

        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $this->apiVersion,
            $this->phoneNumberId
        );

        try {
            $response = $this->httpClient->request('POST', $url, [
                'auth_bearer' => $this->accessToken,
                'json' => $payload,
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $body = $response->toArray(false);

                $this->logger->info('WhatsApp : message envoyé', [
                    'template' => $templateName,
                    'messageId' => $body['messages'][0]['id'] ?? null,
                ]);

                return true;
            }

            // getContent(false) évite de transformer une 4xx/5xx en exception :
            // on veut lire le message d'erreur renvoyé par Meta.
            $this->logger->error('WhatsApp : envoi refusé par Meta', [
                'template' => $templateName,
                'status' => $statusCode,
                'response' => $response->getContent(false),
            ]);
        } catch (HttpClientExceptionInterface $e) {
            $this->logger->error('WhatsApp : erreur de transport', [
                'template' => $templateName,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Convertit un numéro saisi librement en E.164 sans "+", format attendu par Meta.
     */
    private function normalizeNumber(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            // Préfixe international à l'ancienne : 0032… → 32…
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            // Format national congolais : 0XXXXXXXXX → 243XXXXXXXXX
            $digits = self::DEFAULT_COUNTRY_CODE . substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            // Numéro congolais sans indicatif ni zéro initial
            $digits = self::DEFAULT_COUNTRY_CODE . $digits;
        }

        // E.164 : 8 à 15 chiffres, jamais de zéro en tête.
        return preg_match('/^[1-9]\d{7,14}$/', $digits) === 1 ? $digits : null;
    }
}
