<?php

namespace App\Service;

use App\Entity\News;
use App\Manager\NewsletterManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class NewsletterDistributionService
{
    public function __construct(
        private EmailService $emailService,
        private NewsletterManager $newsletterManager,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Envoie une annonce à tous les abonnés actifs de la newsletter
     */
    public function sendNewsToSubscribers(News $news): array
    {
        if ($news->isSentToNewsletter()) {
            throw new \Exception('Cette annonce a déjà été envoyée à la newsletter');
        }

        $emails = $this->newsletterManager->getActiveEmails();
        $results = [
            'total' => count($emails),
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($emails as $email) {
            try {
                $this->sendNewsToEmail($news, $email);
                $results['sent']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'email' => $email,
                    'error' => $e->getMessage()
                ];
                
                $this->logger->error('Failed to send news to email', [
                    'newsId' => $news->getId(),
                    'email' => $email,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Marquer l'annonce comme envoyée
        $news->setIsSentToNewsletter(true);
        $news->setSentAt(new \DateTime());
        $this->entityManager->flush();

        $this->logger->info('News sent to newsletter subscribers', [
            'newsId' => $news->getId(),
            'total' => $results['total'],
            'sent' => $results['sent'],
            'failed' => $results['failed']
        ]);

        return $results;
    }

    /**
     * Envoie une annonce à un email spécifique
     */
    public function sendNewsToEmail(News $news, string $email): void
    {
        $this->emailService->sendNewsEmail($news, $email);
    }
}

