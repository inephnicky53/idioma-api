<?php

namespace App\Dto;

use App\Enum\Currency;
use App\Enum\PaymentMethod;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * DTO pour l'inscription des membres du club
 * Supporte l'inscription simple ou avec paiement
 */
#[Assert\Callback('validate')]
final class RegisterDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'email est requis')]
        #[Assert\Email(message: 'L\'email n\'est pas valide')]
        public ?string $email = null,

        #[Assert\NotBlank(message: 'Le mot de passe est requis')]
        #[Assert\Length(min: 6, minMessage: 'Le mot de passe doit contenir au moins 6 caractères')]
        public ?string $password = null,

        #[Assert\NotBlank(message: 'Le prénom est requis')]
        public ?string $firstName = null,

        #[Assert\NotBlank(message: 'Le nom est requis')]
        public ?string $lastName = null,

        /**
         * Numéro de téléphone (optionnel pour inscription simple, obligatoire pour MOBILE)
         */
        public ?string $phone = null,

        /**
         * Numéro de téléphone pour le paiement (obligatoire pour MOBILE)
         */
        public ?string $phonePayment = null,

        /**
         * Niveau du club (optionnel)
         */
        public ?string $level = null,

        /**
         * Type de participation (optionnel)
         */
        public ?string $participationType = null,

        /**
         * Plan d'abonnement (optionnel, requis si paymentMethod est fourni)
         */
        public ?int $subscriptionPlanId = null,

        /**
         * Méthode de paiement: MOBILE, BANK, CASH (optionnel)
         */
        public ?string $paymentMethod = null,

        /**
         * Devise pour le paiement (optionnel, défaut: devise du plan)
         */
        public ?Currency $currency = null,
    ) {}

    /**
     * Validation conditionnelle selon la méthode de paiement
     */
    public function validate(ExecutionContextInterface $context): void
    {
        // Si paymentMethod est fourni, valider les champs de paiement
        if ($this->paymentMethod) {
            // Valider la méthode de paiement
            $validMethods = ['MOBILE', 'CASH', 'BANK'];
            if (!in_array(strtoupper($this->paymentMethod), $validMethods)) {
                $context->buildViolation('Méthode de paiement invalide')
                    ->atPath('paymentMethod')
                    ->addViolation();
                return;
            }

            // Le plan d'abonnement est requis si paiement
            if (!$this->subscriptionPlanId) {
                $context->buildViolation('Le plan d\'abonnement est requis pour un paiement')
                    ->atPath('subscriptionPlanId')
                    ->addViolation();
            }

            // Pour MOBILE, le téléphone de paiement est obligatoire
            if (strtoupper($this->paymentMethod) === 'MOBILE') {
                if (empty($this->phonePayment)) {
                    $context->buildViolation('Le numéro de téléphone est requis pour un paiement mobile')
                        ->atPath('phonePayment')
                        ->addViolation();
                } else {
                    // Valider le format du numéro (RDC)
                    $cleanedPhone = preg_replace('/[\s\-()]+/', '', $this->phonePayment);
                    if (!preg_match('/^(\+?243|0)?[0-9]{9}$/', $cleanedPhone)) {
                        $context->buildViolation('Format de numéro invalide (ex: +243823232839)')
                            ->atPath('phonePayment')
                            ->addViolation();
                    }
                }
            }
        }
    }

    /**
     * Retourne la méthode de paiement en enum
     */
    public function getPaymentMethodEnum(): ?PaymentMethod
    {
        if (!$this->paymentMethod) {
            return null;
        }
        return PaymentMethod::fromString($this->paymentMethod);
    }

    /**
     * Vérifie si c'est une inscription avec paiement
     */
    public function hasPayment(): bool
    {
        return !empty($this->paymentMethod) && !empty($this->subscriptionPlanId);
    }
}

