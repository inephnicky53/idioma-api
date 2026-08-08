<?php

namespace App\Message;

/**
 * Dispatché lorsqu'un abonnement actif atteint sa date de fin sans renouvellement.
 */
final readonly class SendSubscriptionExpiredMessage
{
    public function __construct(
        public int $subscriptionId,
    ) {}
}
