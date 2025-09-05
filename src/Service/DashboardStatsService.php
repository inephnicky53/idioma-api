<?php

namespace App\Service;

use App\ApiResource\StatsResource;
use App\Entity\Teacher;
use App\Entity\User;
use App\Repository\PlanningRepository;
use App\Repository\UserCourseRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class DashboardStatsService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserCourseRepository $userCourseRepository,
        private readonly PlanningRepository $planningRepository
    ) 
    {
    }

    public function getDashboardStats(): StatsResource
    {
        $stats = new StatsResource();

        // Statistiques utilisateurs optimisées
        $userStats = $this->getUserStats();
        $stats->total['users'] = $userStats['total'];
        $stats->total['students'] = $userStats['students'];
        
        // Statistiques professeurs
        $teacherStats = $this->getTeacherStats();
        $stats->total['teachers'] = [
            'value' => $teacherStats['total'],
            'waiting' => $teacherStats['waiting']
        ];

        // Statistiques cours
        $courseStats = $this->getCourseStats();
        $stats->total['courses'] = $courseStats['total'];
        $stats->total['waiting_courses'] = $courseStats['waiting'];
        $stats->total['hours_courses'] = $courseStats['hours'];

        return $stats;
    }

    public function getMonthlyUserStats(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(u.id) as count, MONTH(u.createdAt) as month')
           ->from(User::class, 'u')
           ->where('u.createdAt >= :startDate')
           ->setParameter('startDate', new \DateTime('-6 months'))
           ->groupBy('month')
           ->orderBy('month', 'ASC');

        $results = $qb->getQuery()->getResult();
        
        // Formatage des données pour le graphique
        $data = array_fill(0, 6, 0);
        foreach ($results as $result) {
            $monthIndex = (int)$result['month'] - 1;
            if ($monthIndex >= 0 && $monthIndex < 6) {
                $data[$monthIndex] = (int)$result['count'];
            }
        }
        
        return $data;
    }

    public function getMonthlyCourseStats(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(uc.id) as count, MONTH(uc.addedAt) as month')
           ->from('App\Entity\UserCourse', 'uc')
           ->where('uc.startAt >= :startDate')
           ->setParameter('startDate', new \DateTime('-6 months'))
           ->groupBy('month')
           ->orderBy('month', 'ASC');

        $results = $qb->getQuery()->getResult();
        
        $data = array_fill(0, 6, 0);
        foreach ($results as $result) {
            $monthIndex = (int)$result['month'] - 1;
            if ($monthIndex >= 0 && $monthIndex < 6) {
                $data[$monthIndex] = (int)$result['count'];
            }
        }
        
        return $data;
    }

    private function getUserStats(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(u.id) as total')
           ->addSelect('SUM(CASE WHEN u.roles LIKE :studentRole THEN 1 ELSE 0 END) as students')
           ->from(User::class, 'u')
           ->setParameter('studentRole', '%"' . User::STUDENT . '"%');

        return $qb->getQuery()->getSingleResult();
    }

    private function getTeacherStats(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(t.id) as total')
           ->addSelect('SUM(CASE WHEN t.status = :waitingStatus THEN 1 ELSE 0 END) as waiting')
           ->from(Teacher::class, 't')
           ->setParameter('waitingStatus', Teacher::STATUS_WAITING);

        return $qb->getQuery()->getSingleResult();
    }

    private function getCourseStats(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(uc.id) as total')
           ->addSelect('SUM(CASE WHEN uc.status = :waitingStatus THEN 1 ELSE 0 END) as waiting')
           ->from('App\Entity\UserCourse', 'uc')
           ->setParameter('waitingStatus', 'waiting'); // Ajustez selon votre logique

        $result = $qb->getQuery()->getSingleResult();
        
        return [
            'total' => (int)$result['total'],
            'waiting' => (int)$result['waiting'],
            'hours' => (int)($result['hours'] ?? 0)
        ];
    }
}