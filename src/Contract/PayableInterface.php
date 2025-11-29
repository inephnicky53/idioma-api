<?php

namespace App\Contract;

use App\Enum\Currency;
use App\Enum\PurchaseType;

/**
 * Interface pour les entités qui peuvent être achetées via un paiement
 */
interface PayableInterface
{
    /**
     * Identifiant unique de l'entité
     */
    public function getId(): ?int;

    /**
     * Nom/titre du produit pour affichage
     */
    public function getLabel(): string;

    /**
     * Prix du produit
     */
    public function getPrice(): string;

    /**
     * Devise du produit
     */
    public function getCurrency(): Currency;

    /**
     * Type d'achat (abonnement ou cours)
     */
    public function getPurchaseType(): PurchaseType;

    /**
     * Vérifie si le produit est disponible à l'achat
     */
    public function isAvailableForPurchase(): bool;
}

