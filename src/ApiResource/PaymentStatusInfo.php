<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\State\Provider\PaymentStatusProvider;

/**
 * Ressource API Platform pour exposer les statuts de paiement
 * Entité virtuelle (non persistée en base)
 */
#[ApiResource(
    shortName: 'PaymentStatus',
    operations: [
        new GetCollection(
            uriTemplate: '/payment-statuses',
            provider: PaymentStatusProvider::class,
            description: 'Liste tous les statuts de paiement avec leurs informations (couleur, icône, label)'
        ),
        new Get(
            uriTemplate: '/payment-statuses/{code}',
            provider: PaymentStatusProvider::class,
            description: 'Récupère les informations d\'un statut de paiement spécifique'
        ),
    ],
    paginationEnabled: false
)]
class PaymentStatusInfo
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $label,
        public readonly string $labelEn,
        public readonly string $color,
        public readonly string $icon,
        public readonly string $description,
        public readonly string $descriptionEn,
        public readonly bool $isFinal,
        public readonly bool $isSuccess,
    ) {}

    /**
     * Identifiant unique pour API Platform
     */
    public function getId(): string
    {
        return $this->code;
    }
}

