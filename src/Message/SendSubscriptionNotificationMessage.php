<?php

namespace App\Message;

/**
 * Dispatché après l'activation ou la prolongation d'un abonnement.
 * Le handler notifie le client (email + WhatsApp) en arrière-plan.
 */
final readonly class SendSubscriptionNotificationMessage
{
    public function __construct(
        public int $subscriptionId,
        /** Distingue une prolongation d'un premier abonnement. */
        public bool $renewed = false,
    ) {}
}
