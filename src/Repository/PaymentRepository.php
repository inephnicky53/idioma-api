<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\User;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
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
        return $this->findBy(['user' => $user, 'status' => PaymentStatus::COMPLETED], ['paidAt' => 'DESC']);
    }

    public function findPendingByUser(User $user): array
    {
        return $this->findBy(['user' => $user, 'status' => PaymentStatus::WAIT]);
    }

    public function getTotalRevenueToday(): float
    {
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = (clone $today)->modify('+1 day');

        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->where('p.status = :status')
            ->andWhere('p.createdAt >= :today')
            ->andWhere('p.createdAt < :tomorrow')
            ->setParameter('status', PaymentStatus::COMPLETED)
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
            ->andWhere('p.createdAt >= :start')
            ->andWhere('p.createdAt < :end')
            ->setParameter('status', PaymentStatus::COMPLETED)
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
            ->andWhere('p.createdAt >= :start')
            ->andWhere('p.createdAt <= :end')
            ->setParameter('status', PaymentStatus::COMPLETED)
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
            ->andWhere('p.createdAt >= :start')
            ->andWhere('p.createdAt <= :end')
            ->setParameter('status', PaymentStatus::COMPLETED)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getWaitPaymentsCount(DateTime $startDate, DateTime $endDate): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->andWhere('p.createdAt >= :start')
            ->andWhere('p.createdAt <= :end')
            ->setParameter('status', PaymentStatus::WAIT)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getFailedPaymentsCount(DateTime $startDate, DateTime $endDate): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->andWhere('p.createdAt >= :start')
            ->andWhere('p.createdAt <= :end')
            ->setParameter('status', PaymentStatus::FAILED)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getCashPaymentsCount(DateTime $startDate, DateTime $endDate): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.paymentMethod = :method')
            ->andWhere('p.createdAt >= :start')
            ->andWhere('p.createdAt <= :end')
            ->setParameter('method', PaymentMethod::CASH)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getCashWaitPaymentsCount(DateTime $startDate, DateTime $endDate): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.paymentMethod = :method')
            ->andWhere('p.status = :status')
            ->andWhere('p.createdAt >= :start')
            ->andWhere('p.createdAt <= :end')
            ->setParameter('method', PaymentMethod::CASH)
            ->setParameter('status', PaymentStatus::WAIT)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getCashCompletedPaymentsCount(DateTime $startDate, DateTime $endDate): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.paymentMethod = :method')
            ->andWhere('p.status = :status')
            ->andWhere('p.createdAt >= :start')
            ->andWhere('p.createdAt <= :end')
            ->setParameter('method', PaymentMethod::CASH)
            ->setParameter('status', PaymentStatus::COMPLETED)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

