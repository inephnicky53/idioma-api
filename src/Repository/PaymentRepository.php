<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\User;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['paidAt' => 'DESC']);
    }

    public function findCompletedByUser(User $user): array
    {
        return $this->findBy(['user' => $user, 'status' => 'completed'], ['paidAt' => 'DESC']);
    }

    public function findPendingByUser(User $user): array
    {
        return $this->findBy(['user' => $user, 'status' => 'pending']);
    }

    public function getTotalRevenueToday(): float
    {
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = (clone $today)->modify('+1 day');

        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->where('p.status = :status')
            ->andWhere('p.paidAt >= :today')
            ->andWhere('p.paidAt < :tomorrow')
            ->setParameter('status', 'completed')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getOneOrNullResult();

        return (float) ($result['total'] ?? 0);
    }

    public function getTotalRevenueThisWeek(): float
    {
        $today = new DateTime();
        $startOfWeek = (clone $today)->modify('monday this week')->setTime(0, 0, 0);
        $endOfWeek = (clone $startOfWeek)->modify('+7 days');

        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->where('p.status = :status')
            ->andWhere('p.paidAt >= :start')
            ->andWhere('p.paidAt < :end')
            ->setParameter('status', 'completed')
            ->setParameter('start', $startOfWeek)
            ->setParameter('end', $endOfWeek)
            ->getQuery()
            ->getOneOrNullResult();

        return (float) ($result['total'] ?? 0);
    }

    public function getTotalRevenueThisMonth(): float
    {
        $today = new DateTime();
        $startOfMonth = (clone $today)->modify('first day of this month')->setTime(0, 0);
        $endOfMonth = (clone $today)->modify('last day of this month')->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->where('p.status = :status')
            ->andWhere('p.paidAt >= :start')
            ->andWhere('p.paidAt <= :end')
            ->setParameter('status', 'completed')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->getQuery()
            ->getOneOrNullResult();

        return (float) ($result['total'] ?? 0);
    }

    public function getCompletedPaymentsCount(DateTime $startDate, DateTime $endDate): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->andWhere('p.paidAt >= :start')
            ->andWhere('p.paidAt <= :end')
            ->setParameter('status', 'completed')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

