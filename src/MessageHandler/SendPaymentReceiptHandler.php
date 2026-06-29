<?php

namespace App\MessageHandler;

use App\Entity\Payment;
use App\Message\SendPaymentReceiptMessage;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Envoie l'email de reçu de paiement de façon asynchrone.
 */
#[AsMessageHandler]
final readonly class SendPaymentReceiptHandler
{
    public function __construct(
        private EmailService           $emailService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface        $logger,
    ) {}

    public function __invoke(SendPaymentReceiptMessage $message): void
    {
        $payment = $this->entityManager->getRepository(Payment::class)->find($message->paymentId);

        if (!$payment) {
            $this->logger->warning('SendPaymentReceiptHandler: payment introuvable', [
                'paymentId' => $message->paymentId,
            ]);
            return;
        }

        $this->emailService->sendPaymentReceiptEmail($payment);
    }
}
