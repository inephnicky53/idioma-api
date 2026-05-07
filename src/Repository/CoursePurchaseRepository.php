<?php

namespace App\Repository;

use App\Entity\CoursePurchase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CoursePurchase>
 */
class CoursePurchaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CoursePurchase::class);
    }

    /**
     * Vérifie si un utilisateur a acheté un cours
     */
    public function hasUserPurchasedCourse(int $userId, int $courseId): bool
    {
        $result = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.user = :userId')
            ->andWhere('p.course = :courseId')
            ->andWhere('p.isActive = :active')
            ->setParameter('userId', $userId)
            ->setParameter('courseId', $courseId)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Trouve tous les achats d'un utilisateur
     * @return CoursePurchase[]
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :userId')
            ->andWhere('p.isActive = :active')
            ->setParameter('userId', $userId)
            ->setParameter('active', true)
            ->orderBy('p.purchasedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

