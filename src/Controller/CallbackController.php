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
     * Codes FlexPay (transaction status):
     * - 0: Succès (paiement approuvé)
     * - 1: Échec (paiement refusé)
     * - 2: En attente
     * - 3: Remboursement en cours
     * - 4: Remboursé
     * - 5: Annulé par marchand
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

        // Code annoncé dans le corps du callback (NON fiable : conservé pour audit uniquement).
        $code = $data['code'] ?? null;
        $payment->setResponsedAt(new DateTimeImmutable());

        // Storer les détails supplémentaires dans le champ data
        $paymentData = $payment->getData() ?? [];
        $paymentData['flexpay_callback'] = [
            'orderNumber' => $data['orderNumber'] ?? null,
            'provider_reference' => $data['provider_reference'] ?? null,
            'amountCustomer' => $data['amountCustomer'] ?? null,
            'phone' => $data['phone'] ?? null,
            'channel' => $data['channel'] ?? null,
            'createdAt' => $data['createdAt'] ?? null,
        ];
        $payment->setData($paymentData);

        // Mettre à jour providerReference si disponible
        if (isset($data['orderNumber'])) {
            $payment->setProviderReference($data['orderNumber']);
        }

        // Ajouter les détails de la réponse
        if (isset($data['message'])) {
            $existingNotes = $payment->getNotes() ?? '';
            $payment->setNotes(trim($existingNotes . "\nFlexPay: " . $data['message']));
        }

        // SÉCURITÉ : on ne fait pas confiance au code annoncé dans le callback
        // (n'importe qui connaissant une référence pourrait forger un faux succès).
        // On vérifie le statut réel directement auprès de FlexPay via son API /check,
        // authentifiée par notre token. C'est cette vérification qui fait autorité.
        try {
            $verification = $this->paymentManager->check($payment);
        } catch (\Throwable $e) {
            $this->logger->error('FlexPay callback: verification failed', [
                'paymentId' => $payment->getId(),
                'reference' => $data['reference'],
                'error' => $e->getMessage(),
            ]);
            $verification = false;
        }

        if ($verification === false) {
            // Vérification impossible (réseau, réponse illisible) : on ne débloque
            // rien et on ne conclut PAS à un échec. Le paiement reste en attente
            // et sera résolu par le polling frontend, un prochain callback ou un admin.
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => false,
                'message' => 'Verification pending',
            ], Response::HTTP_ACCEPTED);
        }

        // Statut désormais à jour depuis FlexPay : on n'accorde l'accès que s'il est réussi.
        $verifiedStatus = $payment->getStatus();
        if ($verifiedStatus->isSuccess()) {
            $this->paymentManager->complete($payment);
            $this->paymentManager->activatePurchase($payment);
            // activatePurchase() ne fait que persist() : un flush explicite est requis
            // pour réellement enregistrer l'abonnement / l'achat de cours.
            $this->entityManager->flush();
        } else {
            $this->entityManager->flush();
        }

        $this->logger->info('FlexPay callback processed', [
            'paymentId' => $payment->getId(),
            'announcedCode' => $code,
            'verifiedStatus' => $verifiedStatus->value,
            'purchaseActivated' => $verifiedStatus->isSuccess()
        ]);

        return new JsonResponse([
            'success' => true,
            'paymentId' => $payment->getId(),
            'status' => $payment->getStatus()->value
        ]);
    }
}

