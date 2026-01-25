<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\NewsletterSubscription;
use App\Repository\NewsletterSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class NewsletterSubscriptionProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NewsletterSubscriptionRepository $repository
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof NewsletterSubscription) {
            return $data;
        }

        // Vérifier si l'email existe déjà
        $existing = $this->repository->findByEmail($data->getEmail());
        
        if ($existing) {
            // Si déjà abonné et actif
            if ($existing->getStatus() === 'active') {
                throw new ConflictHttpException('Cet email est déjà abonné à notre newsletter');
            }
            
            // Si désabonné, réactiver
            $existing->setStatus('active');
            $existing->setCreatedAt(new \DateTime());
            $this->entityManager->flush();
            
            return $existing;
        }

        // Nouvel abonnement
        $data->setStatus('active');
        $data->setCreatedAt(new \DateTime());
        
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}