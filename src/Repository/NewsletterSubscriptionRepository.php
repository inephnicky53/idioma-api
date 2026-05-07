<?php

namespace App\Repository;

use App\Entity\NewsletterSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NewsletterSubscription>
 */
class NewsletterSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterSubscription::class);
    }

    /**
     * Trouve tous les abonnements actifs
     * @return NewsletterSubscription[]
     */
    public function findActiveSubscriptions(): array
    {
        return $this->createQueryBuilder('ns')
            ->where('ns.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('ns.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un abonnement par email
     */
    public function findByEmail(string $email): ?NewsletterSubscription
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Compte les abonnements actifs
     */
    public function countActive(): int
    {
        return $this->createQueryBuilder('ns')
            ->select('COUNT(ns.id)')
            ->where('ns.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les abonnements désabonnés
     */
    public function countUnsubscribed(): int
    {
        return $this->createQueryBuilder('ns')
            ->select('COUNT(ns.id)')
            ->where('ns.status = :status')
            ->setParameter('status', 'unsubscribed')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte tous les abonnements
     */
    public function countAll(): int
    {
        return $this->createQueryBuilder('ns')
            ->select('COUNT(ns.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les abonnements par statut
     * @return NewsletterSubscription[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('ns')
            ->where('ns.status = :status')
            ->setParameter('status', $status)
            ->orderBy('ns.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les derniers abonnements
     * @return NewsletterSubscription[]
     */
    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('ns')
            ->orderBy('ns.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les abonnements inactifs
     * @return NewsletterSubscription[]
     */
    public function findInactive(): array
    {
        return $this->findByStatus('inactive');
    }

    /**
     * Trouve les abonnements désabonnés
     * @return NewsletterSubscription[]
     */
    public function findUnsubscribed(): array
    {
        return $this->findByStatus('unsubscribed');
    }

    /**
     * Trouve les abonnements créés dans une période
     * @return NewsletterSubscription[]
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('ns')
            ->where('ns.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('ns.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les nouveaux abonnements du jour
     */
    public function countTodaySubscriptions(): int
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        return $this->createQueryBuilder('ns')
            ->select('COUNT(ns.id)')
            ->where('ns.createdAt >= :today')
            ->andWhere('ns.createdAt < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les nouveaux abonnements du mois
     */
    public function countMonthSubscriptions(): int
    {
        $firstDay = new \DateTime('first day of this month');
        $lastDay = new \DateTime('last day of this month');

        return $this->createQueryBuilder('ns')
            ->select('COUNT(ns.id)')
            ->where('ns.createdAt >= :firstDay')
            ->andWhere('ns.createdAt <= :lastDay')
            ->setParameter('firstDay', $firstDay)
            ->setParameter('lastDay', $lastDay)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Recherche par email (partiel)
     * @return NewsletterSubscription[]
     */
    public function searchByEmail(string $query): array
    {
        return $this->createQueryBuilder('ns')
            ->where('ns.email LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('ns.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des abonnements
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->countAll(),
            'active' => $this->countActive(),
            'unsubscribed' => $this->countUnsubscribed(),
            'today' => $this->countTodaySubscriptions(),
            'month' => $this->countMonthSubscriptions(),
        ];
    }
}

