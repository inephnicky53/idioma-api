<?php

namespace App\Message;

/**
 * Dispatché après l'activation d'un achat de cours.
 * Le handler notifie le client (email + WhatsApp) en arrière-plan.
 */
final readonly class SendCoursePurchaseNotificationMessage
{
    public function __construct(
        public int $coursePurchaseId,
    ) {}
}
