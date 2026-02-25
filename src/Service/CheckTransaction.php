<?php

namespace App\Service;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Service\Payment\PaymentManager;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CheckTransaction
{
    public function __construct(
        private EntityManagerInterface $em,
        private OperatorProcess $process,
        private PaymentManager $paymentManager,
        private LoggerInterface $logger
    ) {}

    /**
     * Vérifie une seule transaction et retourne le résultat
     * @return array|false
     */
    public function checkSinglePayment(Payment $payment): array|false
    {
        return $this->process->check($payment);
    }

    /**
     * Traite une transaction vérifiée et met à jour son statut
     * S'inspire du CallbackController pour la cohérence
     */
    private function processPaymentResult(Payment $payment, array $body): void
    {
        // Vérifier que le paiement n'est pas déjà dans un état final
        if ($payment->getStatus()->isFinal()) {
            $this->logger->info('Payment already in final state', [
                'paymentId' => $payment->getId(),
                'status' => $payment->getStatus()->value
            ]);
            return;
        }

        // Convertir le code FlexPay en statut
        $code = $body['payment']['status'] ?? $body['code'] ?? null;
        $newStatus = PaymentStatus::fromFlexPayCode((string) $code);
        $payment->setStatus($newStatus);
        $payment->setResponsedAt(new DateTimeImmutable());

        // Ajouter les détails de la réponse
        if (isset($body['message'])) {
            $existingNotes = $payment->getNotes() ?? '';
            $payment->setNotes(trim($existingNotes . "\nVérification: " . $body['message']));
        }

        // Si paiement réussi, activer l'achat (abonnement ou cours)
        if ($newStatus->isSuccess()) {
            $this->paymentManager->activatePurchase($payment);
        }

        $this->em->persist($payment);
        $this->em->flush();

        $this->logger->info('Payment processed', [
            'paymentId' => $payment->getId(),
            'status' => $payment->getStatus()->value,
            'purchaseActivated' => $newStatus->isSuccess()
        ]);
    }

    /**
     * Vérifie tous les paiements en attente (WAIT)
     * Retourne le nombre de paiements vérifiés
     */
    public function checkAllPendingPayments(): int
    {
        $paymentRepository = $this->em->getRepository(Payment::class);
        $pendingPayments = $paymentRepository->findBy(['status' => PaymentStatus::WAIT]);

        $this->logger->info('Starting check for pending payments', ['count' => count($pendingPayments)]);

        $processedCount = 0;

        foreach ($pendingPayments as $payment) {
            try {
                $body = $this->checkSinglePayment($payment);

                if ($body !== false) {
                    $this->processPaymentResult($payment, $body);
                    $processedCount++;
                } else {
                    $this->logger->warning('Failed to check payment', ['paymentId' => $payment->getId()]);
                }
            } catch (\Exception $e) {
                $this->logger->error('Error checking payment', [
                    'paymentId' => $payment->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->logger->info('Pending payments check completed', ['processed' => $processedCount]);

        return $processedCount;
    }

    /**
     * Alias pour checkAllPendingPayments (compatibilité)
     */
    public function all(): int
    {
        return $this->checkAllPendingPayments();
    }

}
