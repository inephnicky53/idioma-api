<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Payment;
use App\Service\Payment\PaymentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Processor pour vérifier le statut d'une transaction
 * Utilisé pour interroger le provider de paiement et mettre à jour le statut
 */
readonly class CheckTransactionProcessor implements ProcessorInterface
{
    public function __construct(
        private PaymentManager $paymentManager,
        private Security $security,
        private EntityManagerInterface $entityManager
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Payment
    {
        if (!$data instanceof Payment) {
            throw new \InvalidArgumentException('Expected Payment entity');
        }

        // Vérifier que l'utilisateur est propriétaire du paiement ou admin
        $user = $this->security->getUser();
        if (!$user) {
            throw new AccessDeniedHttpException('Vous devez être connecté');
        }

        if ($data->getUser() !== $user && !$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedHttpException('Vous n\'avez pas accès à ce paiement');
        }

        // Vérifier le statut de la transaction auprès du provider
        $this->paymentManager->check($data);

        // Si paiement réussi et pas encore complété, complétez-le et activez l'achat
        if ($data->getStatus()->isSuccess() && $data->getPaidAt() === null) {
            $this->paymentManager->complete($data);
            $this->paymentManager->activatePurchase($data);
            // activatePurchase() ne fait que persist() : flush explicite requis pour
            // enregistrer l'abonnement / l'achat (le processor custom remplace le
            // persist par défaut d'API Platform).
            $this->entityManager->flush();
        }

        return $data;
    }
}

