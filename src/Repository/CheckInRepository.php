<?php

namespace App\Repository;

use App\Entity\CheckIn;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CheckIn>
 */
class CheckInRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CheckIn::class);
    }

    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['checkedInAt' => 'DESC']);
    }

    public function findTodayCheckIns(User $user): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = (clone $today)->modify('+1 day');

        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->andWhere('c.checkedInAt >= :today')
            ->andWhere('c.checkedInAt < :tomorrow')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getResult();
    }

    public function getTodayCheckInsCount(): int
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = (clone $today)->modify('+1 day');

        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.checkedInAt >= :today')
            ->andWhere('c.checkedInAt < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getThisWeekCheckInsCount(): int
    {
        $today = new \DateTime();
        $startOfWeek = (clone $today)->modify('monday this week')->setTime(0, 0, 0);
        $endOfWeek = (clone $startOfWeek)->modify('+7 days');

        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.checkedInAt >= :start')
            ->andWhere('c.checkedInAt < :end')
            ->setParameter('start', $startOfWeek)
            ->setParameter('end', $endOfWeek)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getThisMonthCheckInsCount(): int
    {
        $today = new \DateTime();
        $startOfMonth = (clone $today)->modify('first day of this month')->setTime(0, 0, 0);
        $endOfMonth = (clone $today)->modify('last day of this month')->setTime(23, 59, 59);

        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.checkedInAt >= :start')
            ->andWhere('c.checkedInAt <= :end')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getActiveCheckInsCount(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.checkedOutAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}

