<?php

namespace App\MessageHandler;

use App\Entity\Subscription;
use App\Message\SendSubscriptionNotificationMessage;
use App\Service\EmailService;
use App\Service\WhatsAppService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Confirme au client l'activation (ou la prolongation) de son abonnement.
 *
 * Email d'abord, WhatsApp ensuite : voir SendWelcomeNotificationHandler pour
 * le raisonnement sur les rejeux Messenger.
 */
#[AsMessageHandler]
final readonly class SendSubscriptionNotificationHandler
{
    public function __construct(
        private EmailService           $emailService,
        private WhatsAppService        $whatsAppService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface        $logger,
    ) {}

    public function __invoke(SendSubscriptionNotificationMessage $message): void
    {
        $subscription = $this->entityManager
            ->getRepository(Subscription::class)
            ->find($message->subscriptionId);

        if (!$subscription) {
            $this->logger->warning('SendSubscriptionNotificationHandler: abonnement introuvable', [
                'subscriptionId' => $message->subscriptionId,
            ]);

            return;
        }

        $this->emailService->sendSubscriptionActivatedEmail($subscription, $message->renewed);

        if (!$this->whatsAppService->sendSubscriptionActivated($subscription)) {
            $this->logger->info('Abonnement : WhatsApp non délivré', [
                'subscriptionId' => $subscription->getId(),
            ]);
        }
    }
}
