<?php

namespace App\MessageHandler;

use App\Entity\Subscription;
use App\Message\SendSubscriptionExpiredMessage;
use App\Service\EmailService;
use App\Service\WhatsAppService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendSubscriptionExpiredHandler
{
    public function __construct(
        private EmailService           $emailService,
        private WhatsAppService        $whatsAppService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface        $logger,
    ) {}

    public function __invoke(SendSubscriptionExpiredMessage $message): void
    {
        $subscription = $this->entityManager
            ->getRepository(Subscription::class)
            ->find($message->subscriptionId);

        if (!$subscription) {
            $this->logger->warning('SendSubscriptionExpiredHandler: abonnement introuvable', [
                'subscriptionId' => $message->subscriptionId,
            ]);

            return;
        }

        $this->emailService->sendSubscriptionExpiredEmail($subscription);

        if (!$this->whatsAppService->sendSubscriptionExpired($subscription)) {
            $this->logger->info('Abonnement expiré : WhatsApp non délivré', [
                'subscriptionId' => $subscription->getId(),
            ]);
        }
    }
}
