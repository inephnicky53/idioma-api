<?php

namespace App\Tests\Service\WhatsApp;

use App\Service\WhatsApp\CloudApiWhatsAppSender;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class CloudApiWhatsAppSenderTest extends TestCase
{
    /**
     * @param array<string, mixed>|null $captured Rempli avec la requête interceptée
     */
    private function makeSender(
        ?MockResponse $response = null,
        ?array &$captured = null,
        ?string $token = 'test-token',
        ?string $phoneNumberId = '123456',
    ): CloudApiWhatsAppSender {
        $capturedRef = &$captured;

        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($response, &$capturedRef) {
            $capturedRef = [
                'method' => $method,
                'url' => $url,
                'body' => json_decode($options['body'] ?? '{}', true),
                'headers' => $options['headers'] ?? [],
            ];

            return $response ?? new MockResponse(
                json_encode(['messages' => [['id' => 'wamid.TEST']]]),
                ['http_code' => 200]
            );
        });

        return new CloudApiWhatsAppSender(
            $client,
            new NullLogger(),
            $token,
            $phoneNumberId,
            'v21.0',
            'fr',
        );
    }

    public function testSendsTemplateWithBodyParameters(): void
    {
        $captured = null;
        $sender = $this->makeSender(captured: $captured);

        $sent = $sender->sendTemplate('0812345678', 'idioma_welcome', ['Nephtali']);

        $this->assertTrue($sent);
        $this->assertSame('POST', $captured['method']);
        $this->assertSame(
            'https://graph.facebook.com/v21.0/123456/messages',
            $captured['url']
        );
        $this->assertSame('whatsapp', $captured['body']['messaging_product']);
        $this->assertSame('idioma_welcome', $captured['body']['template']['name']);
        $this->assertSame('fr', $captured['body']['template']['language']['code']);
        $this->assertSame(
            [['type' => 'text', 'text' => 'Nephtali']],
            $captured['body']['template']['components'][0]['parameters']
        );
    }

    public function testAppendsExtraComponentsAfterBody(): void
    {
        $captured = null;
        $sender = $this->makeSender(captured: $captured);

        $button = [
            'type' => 'button',
            'sub_type' => 'url',
            'index' => '0',
            'parameters' => [['type' => 'text', 'text' => '1234']],
        ];

        $sender->sendTemplate('243812345678', 'idioma_otp', ['1234'], [$button]);

        $components = $captured['body']['template']['components'];
        $this->assertCount(2, $components);
        $this->assertSame('body', $components[0]['type']);
        $this->assertSame($button, $components[1]);
    }

    #[DataProvider('phoneNumberProvider')]
    public function testNormalizesRecipientNumbers(string $input, string $expected): void
    {
        $captured = null;
        $sender = $this->makeSender(captured: $captured);

        $this->assertTrue($sender->sendTemplate($input, 'idioma_welcome', ['X']));
        $this->assertSame($expected, $captured['body']['to']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function phoneNumberProvider(): array
    {
        return [
            'national avec zéro' => ['0812345678', '243812345678'],
            'national sans zéro' => ['812345678', '243812345678'],
            'international avec +' => ['+243812345678', '243812345678'],
            'international avec 00' => ['00243812345678', '243812345678'],
            'déjà normalisé' => ['243812345678', '243812345678'],
            'espaces et tirets' => ['+243 81-234 5678', '243812345678'],
            'hors RDC' => ['+32475123456', '32475123456'],
        ];
    }

    public function testRejectsUnusableNumberWithoutCallingApi(): void
    {
        $captured = null;
        $sender = $this->makeSender(captured: $captured);

        $this->assertFalse($sender->sendTemplate('12', 'idioma_welcome', ['X']));
        $this->assertNull($captured, 'Aucun appel HTTP ne doit partir pour un numéro invalide');
    }

    public function testReturnsFalseWhenMetaRejectsTheMessage(): void
    {
        $captured = null;
        $sender = $this->makeSender(
            new MockResponse(json_encode(['error' => ['message' => 'Template not found']]), ['http_code' => 400]),
            $captured,
        );

        // Un refus de Meta ne doit jamais remonter en exception : le flux métier
        // qui a déclenché la notification doit continuer.
        $this->assertFalse($sender->sendTemplate('243812345678', 'inconnu', ['X']));
    }

    public function testDoesNothingWhenCredentialsAreMissing(): void
    {
        $captured = null;
        $sender = $this->makeSender(captured: $captured, token: null);

        $this->assertFalse($sender->isConfigured());
        $this->assertFalse($sender->sendTemplate('243812345678', 'idioma_welcome', ['X']));
        $this->assertNull($captured, 'Aucun appel HTTP sans identifiants');
    }
}
