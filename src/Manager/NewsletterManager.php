<?php

namespace App\Manager;

use App\Entity\NewsletterSubscription;
use App\Repository\NewsletterSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class NewsletterManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NewsletterSubscriptionRepository $repository
    ) {
    }

    /**
     * Abonne un email à la newsletter
     */
    public function subscribe(string $email): NewsletterSubscription
    {
        // Vérifier si l'email existe déjà
        $existing = $this->repository->findByEmail($email);
        
        if ($existing) {
            // Si déjà actif, erreur
            if ($existing->getStatus() === 'active') {
                throw new ConflictHttpException('Cet email est déjà abonné à notre newsletter');
            }
            
            // Si inactif ou désabonné, réactiver
            $existing->setStatus('active');
            $existing->setCreatedAt(new \DateTime());
            $this->entityManager->flush();
            
            return $existing;
        }

        // Nouvel abonnement
        $subscription = new NewsletterSubscription();
        $subscription->setEmail($email);
        $subscription->setStatus('active');
        $subscription->setCreatedAt(new \DateTime());

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        return $subscription;
    }

    /**
     * Désabonne un email
     */
    public function unsubscribe(string $email): void
    {
        $subscription = $this->repository->findByEmail($email);
        
        if (!$subscription) {
            throw new \Exception('Cet email n\'est pas abonné');
        }

        $subscription->setStatus('unsubscribed');
        $this->entityManager->flush();
    }

    /**
     * Désactive un abonnement
     */
    public function deactivate(NewsletterSubscription $subscription): void
    {
        $subscription->setStatus('inactive');
        $this->entityManager->flush();
    }

    /**
     * Réactive un abonnement
     */
    public function reactivate(NewsletterSubscription $subscription): void
    {
        $subscription->setStatus('active');
        $this->entityManager->flush();
    }

    /**
     * Supprime un abonnement
     */
    public function delete(NewsletterSubscription $subscription): void
    {
        $this->entityManager->remove($subscription);
        $this->entityManager->flush();
    }

    /**
     * Récupère tous les emails actifs pour envoi
     * @return string[]
     */
    public function getActiveEmails(): array
    {
        $subscriptions = $this->repository->findActiveSubscriptions();
        
        return array_map(
            fn(NewsletterSubscription $sub) => $sub->getEmail(),
            $subscriptions
        );
    }

    /**
     * Récupère les statistiques
     */
    public function getStatistics(): array
    {
        return $this->repository->getStatistics();
    }

    /**
     * Vérifie si un email est abonné
     */
    public function isSubscribed(string $email): bool
    {
        $subscription = $this->repository->findByEmail($email);
        
        return $subscription && $subscription->getStatus() === 'active';
    }
}