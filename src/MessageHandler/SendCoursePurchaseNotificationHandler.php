<?php

namespace App\MessageHandler;

use App\Entity\CoursePurchase;
use App\Message\SendCoursePurchaseNotificationMessage;
use App\Service\EmailService;
use App\Service\WhatsAppService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Confirme au client l'accès à un cours qu'il vient d'acheter.
 *
 * Email d'abord, WhatsApp ensuite : voir SendWelcomeNotificationHandler pour
 * le raisonnement sur les rejeux Messenger.
 */
#[AsMessageHandler]
final readonly class SendCoursePurchaseNotificationHandler
{
    public function __construct(
        private EmailService           $emailService,
        private WhatsAppService        $whatsAppService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface        $logger,
    ) {}

    public function __invoke(SendCoursePurchaseNotificationMessage $message): void
    {
        $purchase = $this->entityManager
            ->getRepository(CoursePurchase::class)
            ->find($message->coursePurchaseId);

        if (!$purchase) {
            $this->logger->warning('SendCoursePurchaseNotificationHandler: achat introuvable', [
                'coursePurchaseId' => $message->coursePurchaseId,
            ]);

            return;
        }

        $this->emailService->sendCoursePurchasedEmail($purchase);

        if (!$this->whatsAppService->sendCoursePurchased($purchase)) {
            $this->logger->info('Achat de cours : WhatsApp non délivré', [
                'coursePurchaseId' => $purchase->getId(),
            ]);
        }
    }
}
