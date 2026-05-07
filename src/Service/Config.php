<?php

namespace App\Service;

/**
 * Service de configuration pour les paramètres de l'application
 */
class Config
{
    public function __construct(
        private readonly string $flexpayToken,
        private readonly string $flexpayEndpoint,
        private readonly string $merchantName,
    ) {}

    public function getFlexpayToken(): string
    {
        return $this->flexpayToken;
    }

    public function getFlexpayEndpoint(): string
    {
        return $this->flexpayEndpoint;
    }

    public function getMerchantName(): string
    {
        return $this->merchantName;
    }
}

