<?php

namespace App\Repository;

use App\Entity\Course;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Course>
 */
class CourseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Course::class);
    }

    /**
     * Trouve les cours publiés
     * @return Course[]
     */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les cours achetés par un utilisateur
     * @return Course[]
     */
    public function findPurchasedByUser(int $userId): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.purchases', 'p')
            ->andWhere('p.user = :userId')
            ->andWhere('p.isActive = :active')
            ->setParameter('userId', $userId)
            ->setParameter('active', true)
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

