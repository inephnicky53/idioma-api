<?php

namespace App\Tests\Controller;

use App\Entity\Payment;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CardRedirectControllerTest extends WebTestCase
{
    private function persistCardPayment(KernelBrowser $client, string $reference, string $nonce, PaymentStatus $status = PaymentStatus::WAIT): void
    {
        // On crée l'utilisateur via l'API d'inscription (évite de connaître tous
        // les champs requis de User).
        $email = 'card_' . uniqid() . '@example.com';
        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => 'Test123!@',
            'firstName' => 'Card',
            'lastName' => 'Test',
        ]));
        self::assertResponseStatusCodeSame(201);

        $em = static::getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        $payment = new Payment();
        $payment->setUser($user);
        $payment->setPaymentMethod(PaymentMethod::CARD);
        $payment->setAmount('10.00');
        $payment->setCurrency(Currency::USD);
        $payment->setStatus($status);
        $payment->setReference($reference);
        $payment->setData(['cardRedirectNonce' => $nonce]);

        $em->persist($payment);
        $em->flush();
    }

    public function testValidNonceRendersAutoSubmitFlexPayForm(): void
    {
        $client = static::createClient();
        $reference = 'PAY_FT_' . uniqid();
        $nonce = bin2hex(random_bytes(16));
        $this->persistCardPayment($client, $reference, $nonce);

        $client->request('GET', "/payment/card/$reference/$nonce");

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        // Formulaire auto-soumis vers la page hébergée FlexPay, jeton dans un champ caché.
        self::assertStringContainsString('action="https://cardpayment.flexpay.cd/v2/pay"', $html);
        self::assertStringContainsString('name="authorization"', $html);
        self::assertStringContainsString('method="post"', $html);
    }

    public function testWrongNonceIsForbidden(): void
    {
        $client = static::createClient();
        $reference = 'PAY_FT_' . uniqid();
        $nonce = bin2hex(random_bytes(16));
        $this->persistCardPayment($client, $reference, $nonce);

        $client->request('GET', "/payment/card/$reference/mauvais-nonce");

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnknownReferenceIsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/payment/card/PAY_DOES_NOT_EXIST/whatever');

        self::assertResponseStatusCodeSame(404);
    }
}
