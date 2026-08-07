<?php

namespace App\Message;

/**
 * Dispatché à la première vérification OTP réussie.
 * Le handler envoie le message de bienvenue (email + WhatsApp) en arrière-plan.
 */
final readonly class SendWelcomeNotificationMessage
{
    public function __construct(
        public int $userId,
    ) {}
}
