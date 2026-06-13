<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Service\Payment\PaymentManager;
use DateTimeImmutable;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour les callbacks des processeurs de paiement
 */
class CallbackController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly PaymentManager $paymentManager,
    ) {}

    /**
     * Callback FlexPay - appelé automatiquement par FlexPay après traitement
     *
     * Codes FlexPay:
     * - 0: Succès (paiement approuvé)
     * - 1: Échec (paiement refusé)
     * - 2: En cours de traitement
     */
    #[Route('/callback/flexpay', name: 'callback_flexpay', methods: ['POST'])]
    public function flexpayCallback(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $this->logger->info('FlexPay callback received', ['data' => $data]);

        // Validation des données
        if (!$data || !isset($data['reference'])) {
            $this->logger->error('FlexPay callback: Invalid data', ['data' => $data]);
            return new JsonResponse(['error' => 'Invalid callback data'], Response::HTTP_BAD_REQUEST);
        }

        // Rechercher le paiement
        $payment = $this->entityManager->getRepository(Payment::class)
            ->findOneBy(['reference' => $data['reference']]);

        if (!$payment) {
            $this->logger->warning('FlexPay callback: Payment not found', ['reference' => $data['reference']]);
            return new JsonResponse(['error' => 'Payment not found'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que le paiement n'est pas déjà dans un état final
        if ($payment->getStatus()->isFinal()) {
            $this->logger->info('FlexPay callback: Payment already in final state', [
                'paymentId' => $payment->getId(),
                'status' => $payment->getStatus()->value
            ]);
            return new JsonResponse(['success' => true, 'message' => 'Payment already processed']);
        }

        // Convertir le code FlexPay en statut
        $code = $data['code'] ?? null;
        $newStatus = PaymentStatus::fromFlexPayCode((string) $code);
        $payment->setResponsedAt(new DateTimeImmutable());

        // Ajouter les détails de la réponse
        if (isset($data['message'])) {
            $existingNotes = $payment->getNotes() ?? '';
            $payment->setNotes(trim($existingNotes . "\nFlexPay: " . $data['message']));
        }

        // Si paiement réussi, complete it and activate purchase
        if ($newStatus->isSuccess()) {
            $this->paymentManager->complete($payment);
            $this->paymentManager->activatePurchase($payment);
        } else {
            $payment->setStatus($newStatus);
            $this->entityManager->flush();
        }

        $this->logger->info('FlexPay callback processed', [
            'paymentId' => $payment->getId(),
            'status' => $payment->getStatus()->value,
            'subscriptionActivated' => $newStatus->isSuccess()
        ]);

        return new JsonResponse([
            'success' => true,
            'paymentId' => $payment->getId(),
            'status' => $payment->getStatus()->value
        ]);
    }
}

