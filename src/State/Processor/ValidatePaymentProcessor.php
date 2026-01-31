<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Service\Payment\PaymentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Processor pour valider un paiement (marquer comme complété et activer l'achat)
 * Utilisé par l'admin pour valider les paiements en cash
 */
readonly class ValidatePaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private PaymentManager $paymentManager,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Payment
    {
        if (!$data instanceof Payment) {
            throw new \InvalidArgumentException('Expected Payment entity');
        }

        // Vérifier que l'utilisateur est admin
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedHttpException('Seuls les administrateurs peuvent valider les paiements');
        }

        // Marquer le paiement comme complété
        $this->paymentManager->complete($data);

        // Activer l'achat (crée CoursePurchase ou réactive l'abonnement)
        $this->paymentManager->activatePurchase($data);

        // Flush les changements
        $this->entityManager->flush();

        return $data;
    }
}

