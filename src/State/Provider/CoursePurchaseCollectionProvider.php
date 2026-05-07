<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\CoursePurchase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Provider pour récupérer les achats de cours de l'utilisateur connecté
 * Filtre les achats par utilisateur et retourne uniquement les achats actifs
 */
class CoursePurchaseCollectionProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        if (!$user) {
            return [];
        }

        // Récupérer les achats de cours de l'utilisateur connecté
        $purchases = $this->entityManager->getRepository(CoursePurchase::class)
            ->createQueryBuilder('cp')
            ->where('cp.user = :user')
            ->andWhere('cp.isActive = :active')
            ->setParameter('user', $user)
            ->setParameter('active', true)
            ->orderBy('cp.purchasedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $purchases;
    }
}

