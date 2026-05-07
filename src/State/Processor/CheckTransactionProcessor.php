<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Payment;
use App\Service\Payment\PaymentManager;
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
        private Security $security
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

        return $data;
    }
}

