<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\PaymentStatusInfo;
use App\Enum\PaymentStatus;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provider pour exposer les statuts de paiement via API Platform
 */
class PaymentStatusProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return $this->getAllStatuses();
        }

        // Get single status by code
        $code = $uriVariables['code'] ?? null;
        if (!$code) {
            throw new NotFoundHttpException('Code de statut requis');
        }

        return $this->getStatusByCode($code);
    }

    /**
     * Retourne tous les statuts de paiement
     */
    private function getAllStatuses(): array
    {
        $statuses = [];
        foreach (PaymentStatus::cases() as $status) {
            $statuses[] = $this->createStatusInfo($status);
        }
        return $statuses;
    }

    /**
     * Retourne un statut par son code
     */
    private function getStatusByCode(string $code): PaymentStatusInfo
    {
        $upperCode = strtoupper($code);
        
        foreach (PaymentStatus::cases() as $status) {
            if ($status->name === $upperCode) {
                return $this->createStatusInfo($status);
            }
        }

        throw new NotFoundHttpException(sprintf('Statut de paiement "%s" introuvable', $code));
    }

    /**
     * Crée un objet PaymentStatusInfo depuis un enum PaymentStatus
     */
    private function createStatusInfo(PaymentStatus $status): PaymentStatusInfo
    {
        return new PaymentStatusInfo(
            code: $status->name,
            name: $status->value,
            label: $status->getLabel(),
            labelEn: $status->getLabelEn(),
            color: $status->getColor(),
            icon: $status->getIcon(),
            description: $status->getDescription(),
            descriptionEn: $this->getDescriptionEn($status),
            isFinal: $status->isFinal(),
            isSuccess: $status->isSuccess(),
        );
    }

    /**
     * Description en anglais pour l'API
     */
    private function getDescriptionEn(PaymentStatus $status): string
    {
        return match($status) {
            PaymentStatus::INIT => 'Payment created but not yet sent to processor',
            PaymentStatus::WAIT => 'Waiting for user payment confirmation',
            PaymentStatus::PROCESS => 'Payment is being processed',
            PaymentStatus::COMPLETED => 'Payment completed successfully',
            PaymentStatus::FAILED => 'Payment failed (technical error or timeout)',
            PaymentStatus::REJECTED => 'Payment rejected manually by administrator',
            PaymentStatus::ERROR => 'A technical error occurred',
            PaymentStatus::REFUNDED => 'Payment has been refunded',
            PaymentStatus::CANCELLED => 'Payment cancelled by user',
        };
    }
}

