<?php

namespace App\Dto;

use App\Enum\Currency;
use App\Enum\PaymentMethod;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * DTO pour l'inscription avec paiement en une seule transaction
 * Crée l'utilisateur ET initie le paiement
 */
#[Assert\Callback('validate')]
final class RegisterWithPaymentDto
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

        #[Assert\NotNull(message: 'Le plan d\'abonnement est requis')]
        public ?int $subscriptionPlanId = null,

        #[Assert\NotBlank(message: 'La méthode de paiement est requise')]
        public ?string $paymentMethod = null,

        /**
         * Numéro de téléphone (obligatoire pour MOBILE)
         */
        public ?string $phone = null,

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
        // Valider la méthode de paiement
        $validMethods = ['MOBILE', 'CASH', 'BANK'];
        if ($this->paymentMethod && !in_array(strtoupper($this->paymentMethod), $validMethods)) {
            $context->buildViolation('Méthode de paiement invalide')
                ->atPath('paymentMethod')
                ->addViolation();
            return;
        }

        // Pour MOBILE, le téléphone est obligatoire
        if (strtoupper($this->paymentMethod ?? '') === 'MOBILE') {
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
    }

    /**
     * Retourne la méthode de paiement en enum
     */
    public function getPaymentMethodEnum(): PaymentMethod
    {
        return PaymentMethod::fromString($this->paymentMethod ?? 'CASH');
    }
}

