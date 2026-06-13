<?php

namespace App\Service;

use App\Entity\Payment;
use App\Entity\User;
use Psr\Log\LoggerInterface;

class SmsService
{
    public function __construct(
        private LoggerInterface $logger,
        private string $appName = 'Idioma Club'
    ) {
    }

    /**
     * Send OTP via SMS
     * NOTE: For now, this is a placeholder. You would need to integrate with an SMS provider (e.g., Twilio, AfricasTalking, etc.)
     */
    public function sendOtpSms(User $user, string $otp): void
    {
        $phone = $user->getPhone();
        if (!$phone) {
            $this->logger->warning('No phone number provided for SMS OTP', ['userId' => $user->getId()]);
            return;
        }

        $message = "Votre code de vérification {$this->appName} est: {$otp}. Ce code expire dans 10 minutes.";

        // TODO: Integrate with your SMS provider here
        // Example with Twilio:
        // $twilio = new Client($sid, $token);
        // $twilio->messages->create(
        //     $phone,
        //     [
        //         'from' => $yourTwilioNumber,
        //         'body' => $message
        //     ]
        // );

        $this->logger->info('SMS OTP sent (placeholder)', [
            'userId' => $user->getId(),
            'phone' => $phone,
            'otp' => $otp
        ]);
    }

    /**
     * Send welcome SMS after account verification
     */
    public function sendWelcomeSms(User $user): void
    {
        $phone = $user->getPhone();
        if (!$phone) {
            $this->logger->warning('No phone number provided for welcome SMS', ['userId' => $user->getId()]);
            return;
        }

        $message = "Bienvenue sur {$this->appName} {$user->getFirstName()} ! Votre compte a été vérifié avec succès. Explorez nos offres !";

        // TODO: Integrate with your SMS provider here

        $this->logger->info('Welcome SMS sent (placeholder)', [
            'userId' => $user->getId(),
            'phone' => $phone
        ]);
    }

    /**
     * Send payment receipt SMS
     */
    public function sendPaymentReceiptSms(Payment $payment): void
    {
        $user = $payment->getUser();
        if (!$user) {
            $this->logger->warning('No user for payment receipt SMS', ['paymentId' => $payment->getId()]);
            return;
        }

        $phone = $payment->getPhone() ?? $user->getPhone();
        if (!$phone) {
            $this->logger->warning('No phone number for payment receipt SMS', [
                'paymentId' => $payment->getId(),
                'userId' => $user->getId()
            ]);
            return;
        }

        $amount = $payment->getAmount();
        $currency = $payment->getCurrency()?->value ?? 'USD';
        $reference = $payment->getReference();
        $product = $payment->getSubscriptionPlan()?->getName() ?? $payment->getCourse()?->getName() ?? 'Achat';

        $message = "{$this->appName} - Paiement reçu ! Merci {$user->getFirstName()}. {$product} - {$amount} {$currency}. Référence: {$reference}.";

        // TODO: Integrate with your SMS provider here

        $this->logger->info('Payment receipt SMS sent (placeholder)', [
            'paymentId' => $payment->getId(),
            'userId' => $user->getId(),
            'phone' => $phone,
            'reference' => $reference
        ]);
    }
}
