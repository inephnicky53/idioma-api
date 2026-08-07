<?php

namespace App\Service\Payment;

use App\Entity\Subscription;

/**
 * Résultat de l'activation d'un abonnement.
 *
 * Porte le drapeau « prolongation » que l'entité seule ne permet pas de
 * distinguer une fois la date de fin repoussée, et dont dépend le libellé
 * des notifications envoyées au client.
 */
final readonly class SubscriptionActivation
{
    public function __construct(
        public Subscription $subscription,
        public bool $renewed,
    ) {}
}
