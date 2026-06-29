<?php

namespace App\MessageHandler;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Message\ProcessPaymentMessage;
use App\Service\Payment\PaymentManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Traite le paiement auprès du provider (FlexPay) de façon asynchrone.
 */
#[AsMessageHandler]
final readonly class ProcessPaymentHandler
{
    public function __construct(
        private PaymentManager         $paymentManager,
        private EntityManagerInterface $entityManager,
        private LoggerInterface        $logger,
    ) {}

    public function __invoke(ProcessPaymentMessage $message): void
    {
        $payment = $this->entityManager->getRepository(Payment::class)->find($message->paymentId);

        if (!$payment) {
            $this->logger->warning('ProcessPaymentHandler: payment introuvable', [
                'paymentId' => $message->paymentId,
            ]);
            return;
        }

        // Idempotence : ne traiter que les paiements encore à l'état initial.
        // Évite de relancer une transaction déjà envoyée (callback reçu entre-temps, retry, etc.)
        if ($payment->getStatus() !== PaymentStatus::INIT) {
            $this->logger->info('ProcessPaymentHandler: paiement déjà traité, ignoré', [
                'paymentId' => $payment->getId(),
                'status'    => $payment->getStatus()?->value,
            ]);
            return;
        }

        $this->paymentManager->process($payment, [
            'provider' => $message->provider,
            'operator' => $message->paymentMethod,
            'phone'    => $message->phone,
        ]);

        $this->logger->info('ProcessPaymentHandler: paiement traité', [
            'paymentId' => $payment->getId(),
            'status'    => $payment->getStatus()?->value,
        ]);
    }
}
