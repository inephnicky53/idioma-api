<?php

namespace App\Tests\Service\Payment;

use App\Entity\Payment;
use App\Enum\Currency;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Service\Payment\FlexPayProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Routing\RouterInterface;

/**
 * Vérifie que l'intégration FlexPay correspond à la documentation officielle
 * (API Card V2 /v2/pay et Check transaction /check/{orderNumber}).
 */
class FlexPayProviderTest extends TestCase
{
    private function makeProvider(MockHttpClient $http): FlexPayProvider
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            static function (string $name, array $params = []): string {
                if ($name === 'flexpay_card_redirect') {
                    return 'https://api.idioma.test/payment/card/'
                        . ($params['reference'] ?? '') . '/' . ($params['nonce'] ?? '');
                }

                return 'https://api.idioma.test/callback/flexpay';
            }
        );

        return new FlexPayProvider(
            $em,
            $router,
            $http,
            'TEST_TOKEN',
            'https://backend.flexpay.cd/api/rest/v1',
            'https://cardpayment.flexpay.cd',
            'IDIOMA',
            'https://idioma.test'
        );
    }

    private function makeCardPayment(): Payment
    {
        $payment = new Payment();
        $payment->setPaymentMethod(PaymentMethod::CARD);
        $payment->setReference('PAY_TEST001');
        $payment->setAmount('10.00');
        $payment->setCurrency(Currency::USD);

        return $payment;
    }

    public function testCardTransactionPreparesBackendRedirectWithoutCallingFlexPay(): void
    {
        // L'API carte FlexPay du marchand ne renvoie pas le JSON `{url}` documenté
        // (elle rend sa page HTML hébergée). On ne doit donc PAS l'appeler côté
        // serveur : on prépare une redirection vers notre propre endpoint.
        $httpCalled = false;
        $http = new MockHttpClient(function () use (&$httpCalled) {
            $httpCalled = true;
            return new MockResponse('', ['http_code' => 200]);
        });

        $provider = $this->makeProvider($http);
        $payment = $this->makeCardPayment();

        $provider->createTransaction($payment, 2, []);

        self::assertFalse($httpCalled, 'La carte ne doit déclencher aucun appel HTTP côté serveur');
        self::assertSame(PaymentStatus::WAIT, $payment->getStatus());

        // Un nonce à usage unique est généré et la paymentUrl pointe vers notre backend.
        $data = $payment->getData() ?? [];
        self::assertArrayHasKey('cardRedirectNonce', $data);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $data['cardRedirectNonce']);
        self::assertSame(
            'https://api.idioma.test/payment/card/PAY_TEST001/' . $data['cardRedirectNonce'],
            $data['paymentUrl']
        );
    }

    public function testBuildCardFormParamsMatchesFlexPayHostedPage(): void
    {
        $provider = $this->makeProvider(new MockHttpClient());
        $payment = $this->makeCardPayment();

        $form = $provider->buildCardFormParams($payment);

        // POST form-encoded vers la page hébergée (et non un appel JSON).
        self::assertSame('https://cardpayment.flexpay.cd/v2/pay', $form['action']);

        $fields = $form['fields'];
        self::assertSame('Bearer TEST_TOKEN', $fields['authorization']);
        self::assertSame('IDIOMA', $fields['merchant']);
        self::assertSame('PAY_TEST001', $fields['reference']);
        // Format documenté : unité principale sans décimale décorative.
        self::assertSame('10', $fields['amount']);
        self::assertSame('USD', $fields['currency']);
        self::assertArrayHasKey('callback_url', $fields);
        self::assertStringContainsString('status=approved', $fields['approve_url']);
        self::assertStringContainsString('status=cancelled', $fields['cancel_url']);
        self::assertStringContainsString('status=declined', $fields['decline_url']);
        self::assertStringContainsString('reference=PAY_TEST001', $fields['approve_url']);
    }

    public function testCheckTransactionSuccessMarksCompleted(): void
    {
        // Réponse documentée du check transaction (page 9 : transaction.status "0" = succès)
        $http = new MockHttpClient(function (string $method, string $url) {
            self::assertSame('GET', $method);
            self::assertSame(
                'https://backend.flexpay.cd/api/rest/v1/check/ORDER123',
                $url
            );

            return new MockResponse(json_encode([
                'code' => '0',
                'message' => 'Une transaction trouvée',
                'transaction' => [
                    'orderNumber' => 'ORDER123',
                    'reference' => 'PAY_TEST001',
                    'amount' => '10.0',
                    'amountCustomer' => '10.0',
                    'currency' => 'USD',
                    'createdAt' => '06-02-2021 17:32:46',
                    'status' => '0',
                ],
            ]), ['http_code' => 200]);
        });

        $provider = $this->makeProvider($http);
        $payment = $this->makeCardPayment();
        $payment->setProviderReference('ORDER123');
        $payment->setStatus(PaymentStatus::WAIT);

        $provider->checkTransaction($payment);

        self::assertSame(PaymentStatus::COMPLETED, $payment->getStatus());
        self::assertTrue($payment->getStatus()->isSuccess());
    }

    public function testCheckTransactionNotFoundLeavesStatusUnchanged(): void
    {
        // Réponse documentée quand aucune transaction n'est trouvée (code "1", transaction null)
        $http = new MockHttpClient(fn () => new MockResponse(json_encode([
            'code' => '1',
            'message' => 'Aucune transaction trouvée',
            'transaction' => null,
        ]), ['http_code' => 200]));

        $provider = $this->makeProvider($http);
        $payment = $this->makeCardPayment();
        $payment->setProviderReference('UNKNOWN');
        $payment->setStatus(PaymentStatus::WAIT);

        $provider->checkTransaction($payment);

        // On ne doit surtout pas accorder l'accès : statut inchangé
        self::assertSame(PaymentStatus::WAIT, $payment->getStatus());
    }

    public function testCheckTransactionPendingKeepsPaymentWaiting(): void
    {
        // transaction.status "2" = en attente : le client n'a pas encore validé
        // le push USSD. Le paiement doit rester en attente, pas basculer en échec.
        $http = new MockHttpClient(fn () => new MockResponse(json_encode([
            'code' => '0',
            'message' => 'Une transaction trouvée',
            'transaction' => ['orderNumber' => 'ORDER123', 'status' => '2'],
        ]), ['http_code' => 200]));

        $payment = $this->makeCardPayment();
        $payment->setProviderReference('ORDER123');
        $payment->setStatus(PaymentStatus::WAIT);

        $this->makeProvider($http)->checkTransaction($payment);

        self::assertSame(PaymentStatus::WAIT, $payment->getStatus());
        self::assertFalse($payment->getStatus()->isFinal());
    }

    public function testCheckTransactionHttpErrorLeavesPaymentWaiting(): void
    {
        // Juste après l'initiation, FlexPay peut répondre en erreur HTTP tant que
        // la transaction n'est pas visible. Ne rien conclure : ni exception qui
        // remonte, ni statut final.
        $http = new MockHttpClient(fn () => new MockResponse('<html>error</html>', ['http_code' => 500]));

        $payment = $this->makeCardPayment();
        $payment->setProviderReference('ORDER123');
        $payment->setStatus(PaymentStatus::WAIT);

        $result = $this->makeProvider($http)->checkTransaction($payment);

        self::assertFalse($result);
        self::assertSame(PaymentStatus::WAIT, $payment->getStatus());
    }

    public function testCheckTransactionUnknownStatusIsNotAFailure(): void
    {
        // Statut non documenté : état indéterminé, surtout pas un échec définitif.
        $http = new MockHttpClient(fn () => new MockResponse(json_encode([
            'code' => '0',
            'message' => 'Une transaction trouvée',
            'transaction' => ['orderNumber' => 'ORDER123', 'status' => '9'],
        ]), ['http_code' => 200]));

        $payment = $this->makeCardPayment();
        $payment->setProviderReference('ORDER123');
        $payment->setStatus(PaymentStatus::WAIT);

        $this->makeProvider($http)->checkTransaction($payment);

        self::assertFalse($payment->getStatus()->isFinal());
    }

    public function testCheckTransactionNeverOverwritesAFinalStatus(): void
    {
        // Le callback a déjà tranché : une vérification tardive ne doit pas
        // rouvrir ni réécrire l'état acquis.
        $http = new MockHttpClient(fn () => new MockResponse(json_encode([
            'code' => '0',
            'transaction' => ['orderNumber' => 'ORDER123', 'status' => '1'],
        ]), ['http_code' => 200]));

        $payment = $this->makeCardPayment();
        $payment->setProviderReference('ORDER123');
        $payment->setStatus(PaymentStatus::COMPLETED);

        $this->makeProvider($http)->checkTransaction($payment);

        self::assertSame(PaymentStatus::COMPLETED, $payment->getStatus());
    }

    public function testMobileTransactionPayloadMatchesFlexPayDocumentation(): void
    {
        // Doc rev 1.5 : tous les champs sont des chaînes, `type` compris, et le
        // montant est en unité principale sans décimale décorative ("575", pas
        // "575.00"). Le numéro part sans « + ».
        $sent = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent) {
            self::assertSame('POST', $method);
            self::assertSame('https://backend.flexpay.cd/api/rest/v1/paymentService', $url);
            $sent = json_decode($options['body'], true);

            return new MockResponse(json_encode([
                'code' => '0',
                'message' => 'Transaction envoyée',
                'orderNumber' => 'ORDER999',
            ]), ['http_code' => 200]);
        });

        $payment = new Payment();
        $payment->setPaymentMethod(PaymentMethod::MOBILE);
        $payment->setReference('PAY_TEST_MM');
        $payment->setAmount('575.00');
        $payment->setCurrency(Currency::CDF);
        $payment->setPhone('243810000000');

        $this->makeProvider($http)->createTransaction($payment, 1, ['phone' => '243810000000']);

        self::assertSame('1', $sent['type']);
        self::assertSame('575', $sent['amount']);
        self::assertSame('CDF', $sent['currency']);
        self::assertSame('IDIOMA', $sent['merchant']);
        self::assertSame('243810000000', $sent['phone']);
        self::assertSame('https://api.idioma.test/callback/flexpay', $sent['callbackUrl']);

        // Après une initiation acceptée, le paiement passe en attente.
        self::assertSame(PaymentStatus::WAIT, $payment->getStatus());
        self::assertSame('ORDER999', $payment->getProviderReference());
    }

    public function testMobileTransactionKeepsRealDecimals(): void
    {
        // 0.5 USD reste "0.5" : seul le zéro décoratif disparaît.
        $sent = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent) {
            $sent = json_decode($options['body'], true);

            return new MockResponse(json_encode(['code' => '0', 'orderNumber' => 'ORDER998']), ['http_code' => 200]);
        });

        $payment = new Payment();
        $payment->setPaymentMethod(PaymentMethod::MOBILE);
        $payment->setReference('PAY_TEST_MM2');
        $payment->setAmount('0.50');
        $payment->setCurrency(Currency::USD);
        $payment->setPhone('243810000000');

        $this->makeProvider($http)->createTransaction($payment, 1, ['phone' => '243810000000']);

        self::assertSame('0.5', $sent['amount']);
    }

    public function testMobileTransactionWithHtmlErrorPageFailsCleanly(): void
    {
        // Le mobile money interroge bien FlexPay côté serveur. Si FlexPay renvoie
        // une page HTML (statut 200) au lieu du JSON attendu, le décodeur ne doit
        // pas planter et l'utilisateur ne doit pas voir « Syntax error for … ».
        $html = '<!DOCTYPE html><html><head><title>Error</title></head><body>Error</body></html>';
        $http = new MockHttpClient(fn () => new MockResponse($html, [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'text/html; charset=UTF-8'],
        ]));

        $provider = $this->makeProvider($http);
        $payment = new Payment();
        $payment->setPaymentMethod(PaymentMethod::MOBILE);
        $payment->setReference('PAY_TEST_MM');
        $payment->setAmount('10.00');
        $payment->setCurrency(Currency::USD);
        $payment->setPhone('243810000000');

        $provider->createTransaction($payment, 1, ['phone' => '243810000000']);

        self::assertSame(PaymentStatus::ERROR, $payment->getStatus());
        self::assertStringNotContainsStringIgnoringCase('syntax error', (string) $payment->getNotes());
        self::assertStringContainsString('indisponible', (string) $payment->getNotes());
        self::assertArrayHasKey('flexpay_error', $payment->getData() ?? []);
        self::assertSame(200, $payment->getData()['flexpay_error']['httpStatus']);
    }

    public function testMissingTokenFailsBeforeAnyHttpCall(): void
    {
        $called = false;
        $http = new MockHttpClient(function () use (&$called) {
            $called = true;
            return new MockResponse('', ['http_code' => 200]);
        });

        $em = $this->createMock(EntityManagerInterface::class);
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            static function (string $name, array $params = []): string {
                if ($name === 'flexpay_card_redirect') {
                    return 'https://api.idioma.test/payment/card/'
                        . ($params['reference'] ?? '') . '/' . ($params['nonce'] ?? '');
                }

                return 'https://api.idioma.test/callback/flexpay';
            }
        );

        $provider = new FlexPayProvider(
            $em,
            $router,
            $http,
            '', // jeton FlexPay manquant
            'https://backend.flexpay.cd/api/rest/v1',
            'https://cardpayment.flexpay.cd',
            'IDIOMA',
            'https://idioma.test'
        );
        $payment = $this->makeCardPayment();

        $provider->createTransaction($payment, 2, []);

        self::assertFalse($called, 'Aucune requête HTTP ne doit partir sans jeton FlexPay');
        self::assertSame(PaymentStatus::ERROR, $payment->getStatus());
        self::assertStringContainsString('configuration', strtolower((string) $payment->getNotes()));
    }
}
