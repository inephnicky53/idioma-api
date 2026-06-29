<?php

namespace App\Message;

/**
 * Message dispatché lorsqu'un paiement est complété.
 * Le handler envoie l'email de reçu en arrière-plan.
 */
final readonly class SendPaymentReceiptMessage
{
    public function __construct(
        public int $paymentId,
    ) {}
}
