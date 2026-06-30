<?php

namespace App\Tests\Controller;

use App\Entity\Payment;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Vérifie que le callback FlexPay met bien à jour la transaction, en s'appuyant
 * sur la vérification authentifiée auprès de FlexPay (/check) — jamais sur le
 * code annoncé dans le corps du callback.
 */
class CallbackControllerTest extends WebTestCase
{
    /**
     * Remplace le client HTTP par un mock renvoyant la réponse /check voulue.
     * À appeler AVANT toute requête, pour que le service ne soit pas déjà initialisé.
     */
    private function mockFlexPayCheck(string $transactionStatus): void
    {
        $mock = new MockHttpClient(static fn (): MockResponse => new MockResponse(
            json_encode([
                'code' => '0',
                'message' => 'Une transaction trouvée',
                'transaction' => [
                    'orderNumber' => 'ORDER_OK',
                    'status' => $transactionStatus, // 0 = succès, 1 = échec
                ],
            ]),
            ['http_code' => 200]
        ));

        static::getContainer()->set('http_client', $mock);
    }

    private function createUser(): User
    {
        $em = static::getContainer()->get('doctrine')->getManager();

        $user = new User();
        $user->setEmail('cb_' . uniqid() . '@example.com');
        $user->setPassword('hashed');
        $user->setFirstName('Cb');
        $user->setLastName('Test');
        $user->setRoles(['ROLE_USER']);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createWaitingPayment(User $user, string $reference, PaymentStatus $status = PaymentStatus::WAIT): Payment
    {
        $em = static::getContainer()->get('doctrine')->getManager();

        $payment = new Payment();
        $payment->setUser($user);
        $payment->setPaymentMethod(PaymentMethod::CARD);
        $payment->setAmount('10.00');
        $payment->setCurrency(Currency::USD);
        $payment->setStatus($status);
        $payment->setReference($reference);

        $em->persist($payment);
        $em->flush();

        return $payment;
    }

    public function testSuccessfulCallbackMarksPaymentCompleted(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // FlexPay /check confirmera le succès (status "0"). Mock posé avant toute requête.
        $this->mockFlexPayCheck('0');

        $user = $this->createUser();
        $reference = 'PAY_CB_' . uniqid();
        $this->createWaitingPayment($user, $reference);

        $client->request('POST', '/callback/flexpay', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'reference' => $reference,
            'code' => '0',
            'orderNumber' => 'ORDER_OK',
        ]));

        self::assertResponseIsSuccessful();

        $em = static::getContainer()->get('doctrine')->getManager();
        $payment = $em->getRepository(Payment::class)->findOneBy(['reference' => $reference]);
        self::assertSame(PaymentStatus::COMPLETED, $payment->getStatus());
        self::assertNotNull($payment->getPaidAt());
        self::assertSame('ORDER_OK', $payment->getProviderReference());
    }

    public function testForgedSuccessCallbackIsRejectedWhenFlexPayReportsFailure(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Le corps annonce un succès (code 0) mais FlexPay /check dit échec (status 1).
        // La vérification authentifiée fait autorité : pas d'accès accordé.
        $this->mockFlexPayCheck('1');

        $user = $this->createUser();
        $reference = 'PAY_CB_' . uniqid();
        $this->createWaitingPayment($user, $reference);

        $client->request('POST', '/callback/flexpay', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'reference' => $reference,
            'code' => '0',
            'orderNumber' => 'ORDER_OK',
        ]));

        self::assertResponseIsSuccessful();

        $em = static::getContainer()->get('doctrine')->getManager();
        $payment = $em->getRepository(Payment::class)->findOneBy(['reference' => $reference]);
        self::assertNotSame(PaymentStatus::COMPLETED, $payment->getStatus());
        self::assertNull($payment->getPaidAt());
    }

    public function testCallbackOnAlreadyCompletedPaymentIsIdempotent(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $user = $this->createUser();
        $reference = 'PAY_CB_' . uniqid();
        $this->createWaitingPayment($user, $reference, PaymentStatus::COMPLETED);

        $client->request('POST', '/callback/flexpay', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'reference' => $reference,
            'code' => '0',
            'orderNumber' => 'ORDER_OK',
        ]));

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        self::assertTrue($response['success']);

        $em = static::getContainer()->get('doctrine')->getManager();
        $payment = $em->getRepository(Payment::class)->findOneBy(['reference' => $reference]);
        self::assertSame(PaymentStatus::COMPLETED, $payment->getStatus());
    }

    public function testCallbackWithUnknownReferenceReturns404(): void
    {
        $client = static::createClient();

        $client->request('POST', '/callback/flexpay', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'reference' => 'PAY_DOES_NOT_EXIST',
            'code' => '0',
        ]));

        self::assertResponseStatusCodeSame(404);
    }
}
