<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\SendWelcomeNotificationMessage;
use App\Service\EmailService;
use App\Service\WhatsAppService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Souhaite la bienvenue au client une fois son compte vérifié.
 *
 * L'email part en premier : s'il échoue, l'exception remonte et Messenger
 * rejoue le message, sans avoir encore envoyé le WhatsApp — donc sans doublon.
 * L'envoi WhatsApp, lui, ne lève jamais et signale son échec par son retour.
 */
#[AsMessageHandler]
final readonly class SendWelcomeNotificationHandler
{
    public function __construct(
        private EmailService           $emailService,
        private WhatsAppService        $whatsAppService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface        $logger,
    ) {}

    public function __invoke(SendWelcomeNotificationMessage $message): void
    {
        $user = $this->entityManager->getRepository(User::class)->find($message->userId);

        if (!$user) {
            $this->logger->warning('SendWelcomeNotificationHandler: utilisateur introuvable', [
                'userId' => $message->userId,
            ]);

            return;
        }

        $this->emailService->sendAccountActivatedEmail($user);

        if (!$this->whatsAppService->sendWelcome($user)) {
            $this->logger->info('Bienvenue : WhatsApp non délivré', [
                'userId' => $user->getId(),
            ]);
        }
    }
}
