<?php

namespace App\Service;

use App\Entity\Subscription;
use App\Entity\User;
use App\Message\SendSubscriptionExpiredMessage;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Marque les abonnements arrivés à échéance et notifie les membres.
 */
readonly class SubscriptionExpirationService
{
    public function __construct(
        private SubscriptionRepository   $subscriptionRepository,
        private EntityManagerInterface   $entityManager,
        private MessageBusInterface      $messageBus,
        private LoggerInterface          $logger,
    ) {}

    /**
     * @return int Nombre d'abonnements expirés traités
     */
    public function processExpiredSubscriptions(): int
    {
        $expired = $this->subscriptionRepository->findActiveSubscriptionsPastEndDate();
        $processed = 0;

        foreach ($expired as $subscription) {
            try {
                $this->expireSubscription($subscription);
                ++$processed;
            } catch (\Throwable $e) {
                $this->logger->error('Échec expiration abonnement', [
                    'subscriptionId' => $subscription->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    private function expireSubscription(Subscription $subscription): void
    {
        $user = $subscription->getUser();
        $plan = $subscription->getPlan();

        $subscription->setStatus('expired');
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        if ($user && $plan?->getType() === 'club') {
            $this->refreshClubMembership($user);
            $this->entityManager->flush();
        }

        $this->messageBus->dispatch(new SendSubscriptionExpiredMessage(
            subscriptionId: $subscription->getId(),
        ));

        $this->logger->info('Abonnement expiré', [
            'subscriptionId' => $subscription->getId(),
            'userId' => $user?->getId(),
        ]);
    }

    private function refreshClubMembership(User $user): void
    {
        $hasActiveClub = (bool) $this->subscriptionRepository->countActiveClubSubscriptionsForUser($user);
        $user->setIsClubMember($hasActiveClub);
        $this->entityManager->persist($user);
    }
}
