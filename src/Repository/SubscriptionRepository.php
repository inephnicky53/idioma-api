<?php

namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function findActiveByUser(User $user): ?Subscription
    {
        return $this->findOneBy(['user' => $user, 'status' => 'active']);
    }

    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    public function findExpiredSubscriptions(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.endDate < :now')
            ->andWhere('s.status != :cancelled')
            ->setParameter('now', new \DateTime())
            ->setParameter('cancelled', 'cancelled')
            ->getQuery()
            ->getResult();
    }

    /**
     * Abonnements encore marqués actifs alors que la date de fin est dépassée.
     *
     * @return Subscription[]
     */
    public function findActiveSubscriptionsPastEndDate(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :active')
            ->andWhere('s.endDate < :now')
            ->setParameter('active', 'active')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    public function countActiveClubSubscriptionsForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->join('s.plan', 'p')
            ->where('s.user = :user')
            ->andWhere('s.status = :active')
            ->andWhere('p.type = :club')
            ->setParameter('user', $user)
            ->setParameter('active', 'active')
            ->setParameter('club', 'club')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getActiveSubscriptionsCount(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getNewSubscriptionsCount(\DateTime $startDate, \DateTime $endDate): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.createdAt >= :start')
            ->andWhere('s.createdAt <= :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getExpiredSubscriptionsCount(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.endDate < :now')
            ->andWhere('s.status != :cancelled')
            ->setParameter('now', new \DateTime())
            ->setParameter('cancelled', 'cancelled')
            ->getQuery()
            ->getSingleScalarResult();
    }
}

