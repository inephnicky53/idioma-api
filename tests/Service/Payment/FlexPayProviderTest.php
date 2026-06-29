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
        $router->method('generate')->willReturn('https://api.idioma.test/callback/flexpay');

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

    public function testCardTransactionMatchesV2Documentation(): void
    {
        $captured = null;

        // Réponse documentée du service de paiement carte V2 (page 7 de la doc)
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured) {
            $captured = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse(json_encode([
                'code' => '0',
                'message' => 'Redirection en cours',
                'orderNumber' => 'LeR4frf04509172137498452',
                'url' => 'https://cardpayment.flexpay.cd/pay/abc123',
            ]), ['http_code' => 200]);
        });

        $provider = $this->makeProvider($http);
        $payment = $this->makeCardPayment();

        $provider->createTransaction($payment, 2, []);

        // URL et méthode conformes à la doc
        self::assertSame('POST', $captured['method']);
        self::assertSame('https://cardpayment.flexpay.cd/v2/pay', $captured['url']);

        // Le corps contient bien tous les champs requis par la doc, dont "authorization"
        $body = json_decode($captured['options']['body'], true);
        self::assertSame('Bearer TEST_TOKEN', $body['authorization']);
        self::assertSame('IDIOMA', $body['merchant']);
        self::assertSame('PAY_TEST001', $body['reference']);
        self::assertSame('10.00', $body['amount']);
        self::assertSame('USD', $body['currency']);
        self::assertArrayHasKey('callback_url', $body);
        self::assertArrayHasKey('approve_url', $body);
        self::assertArrayHasKey('cancel_url', $body);
        self::assertArrayHasKey('decline_url', $body);

        // Effets attendus sur le paiement
        self::assertSame(PaymentStatus::WAIT, $payment->getStatus());
        self::assertSame('LeR4frf04509172137498452', $payment->getProviderReference());
        self::assertSame('https://cardpayment.flexpay.cd/pay/abc123', $payment->getData()['paymentUrl']);
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
}
