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
        // NB : on ne sort pas ici sur un statut déjà final. FlexPayProvider::checkTransaction()
        // vient précisément d'écrire ce statut à partir de la réponse ; c'est donc
        // le cas nominal du succès. L'idempotence est portée par `paidAt` plus bas.

        // Seul `transaction.status` porte l'issue du paiement. Le champ `code`
        // de premier niveau décrit la requête ("0" = requête traitée) : l'utiliser
        // comme statut marquait les paiements en attente COMPLETED (code "0") ou
        // FAILED (code "1", « aucune transaction trouvée ») à tort.
        $statusCode = $body['transaction']['status'] ?? null;

        if (((string) ($body['code'] ?? '')) !== "0" || $statusCode === null) {
            $this->logger->info('Payment check inconclusive, still pending', [
                'paymentId' => $payment->getId(),
                'code' => $body['code'] ?? null,
            ]);
            return;
        }

        $newStatus = PaymentStatus::fromFlexPayCode((string) $statusCode);
        $payment->setStatus($newStatus);
        $payment->setResponsedAt(new DateTimeImmutable());

        // Ajouter les détails de la réponse
        if (isset($body['message'])) {
            $existingNotes = $payment->getNotes() ?? '';
            $payment->setNotes(trim($existingNotes . "\nVérification: " . $body['message']));
        }

        // Si paiement réussi, activer l'achat (abonnement ou cours) et définir paidAt.
        // `paidAt` rend l'opération idempotente : si le callback FlexPay est déjà
        // passé par là, on ne réactive pas l'achat une seconde fois.
        $activated = $newStatus->isSuccess() && $payment->getPaidAt() === null;
        if ($activated) {
            $payment->setPaidAt(new DateTimeImmutable());
            $this->paymentManager->activatePurchase($payment);
        }

        $this->em->persist($payment);
        $this->em->flush();

        $this->logger->info('Payment processed', [
            'paymentId' => $payment->getId(),
            'status' => $payment->getStatus()->value,
            'purchaseActivated' => $activated
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
