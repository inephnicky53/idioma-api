<?php

namespace App\Message;

/**
 * Message dispatché après la création d'un paiement.
 * Le handler appelle le provider externe (FlexPay) en arrière-plan,
 * ce qui évite de bloquer la requête HTTP pendant l'appel réseau.
 */
final readonly class ProcessPaymentMessage
{
    public function __construct(
        public int $paymentId,
        public string $paymentMethod,
        public ?string $phone = null,
        public ?string $provider = null,
    ) {}
}
