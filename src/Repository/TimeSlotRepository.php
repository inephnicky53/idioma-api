<?php

namespace App\Repository;

use App\Entity\TimeSlot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeSlot>
 */
class TimeSlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeSlot::class);
    }

    /**
     * @return TimeSlot[] Returns an array of TimeSlot objects
     */
    public function findByDay(string $day): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.day = :day')
            ->setParameter('day', $day)
            ->orderBy('t.time', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findOneByDayAndTime(string $day, string $time): ?TimeSlot
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.day = :day')
            ->andWhere('t.time = :time')
            ->setParameter('day', $day)
            ->setParameter('time', $time)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
