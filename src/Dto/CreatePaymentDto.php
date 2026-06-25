<?php

namespace App\Dto;

use App\Entity\SubscriptionPlan;
use App\Enum\Currency;
use App\Enum\MobileOperator;
use App\Enum\PaymentMethod;
use App\Enum\PurchaseType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * DTO pour la création d'un paiement
 * L'utilisateur est récupéré automatiquement via le JWT
 * Le montant est calculé depuis le plan d'abonnement
 *
 * Champs requis selon la méthode de paiement:
 * - MOBILE: phone (obligatoire), operator (obligatoire)
 * - CARD: aucun champ obligatoire
 * - CASH: notes (optionnel)
 */
#[Assert\Callback('validate')]
final class CreatePaymentDto
{
    public function __construct(
        #[Assert\NotNull(message: 'Le type d\'achat est requis')]
        public ?PurchaseType $purchaseType = null,

        #[Assert\NotNull(message: 'Le id de l\'achat est requis')]
        public int        $purchaseId,

        #[Assert\NotNull(message: 'La devise est requise')]
        public ?Currency     $currency = null,

        /**
         * Méthode de paiement: MOBILE, CARD, CASH
         */
        #[Assert\NotBlank(message: 'La méthode de paiement est requise')]
        public ?string       $paymentMethod = null,

        /**
         * Numéro de téléphone (obligatoire pour MOBILE)
         */
        public ?string $phone = null,
    ) {}

    /**
     * Validation conditionnelle selon la méthode de paiement
     */
    public function validate(ExecutionContextInterface $context): void
    {
        match (strtoupper($this->paymentMethod)) {
            'MOBILE' => $this->validateMobile($context),
            'CARD', 'BANK' => $this->validateCardOrBank($context),
            'CASH' => $this->validateCash($context),
            default => null,
        };
    }

    /**
     * Validation pour paiement MOBILE
     */
    private function validateMobile(ExecutionContextInterface $context): void
    {
        // Phone obligatoire
        if (empty($this->phone)) {
            $context->buildViolation('Le numéro de téléphone est requis pour un paiement mobile')
                ->atPath('phone')
                ->addViolation();
        } else {
            // Valider le format du numéro (RDC)
            $cleanedPhone = preg_replace('/[\s\-()]+/', '', $this->phone);
            if (!preg_match('/^(\+?243|0)?[0-9]{9}$/', $cleanedPhone)) {
                $context->buildViolation('Format de numéro invalide (ex: +243823232839)')
                    ->atPath('phone')
                    ->addViolation();
            }
        }
    }

    /**
     * Validation pour paiement CARD/BANK
     */
    private function validateCardOrBank(ExecutionContextInterface $context): void
    {
        // No extra fields needed for card/bank payments
        // We just need to recognize it as a valid method
    }

    /**
     * Validation pour paiement CASH
     */
    private function validateCash(ExecutionContextInterface $context): void
    {
        // Pas de champ obligatoire pour CASH
        // Le paiement sera confirmé manuellement par un admin
    }
}

