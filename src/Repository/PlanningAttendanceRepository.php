<?php

namespace App\Repository;

use App\Entity\Planning;
use App\Entity\PlanningAttendance;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanningAttendance>
 */
class PlanningAttendanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanningAttendance::class);
    }

    public function findOneFor(Planning $planning, User $student): ?PlanningAttendance
    {
        return $this->findOneBy([
            'planning' => $planning,
            'student' => $student,
        ]);
    }

    /** @return list<PlanningAttendance> */
    public function findForPlanning(Planning $planning): array
    {
        return $this->findBy(['planning' => $planning], ['id' => 'ASC']);
    }
}
