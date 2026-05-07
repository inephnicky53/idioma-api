<?php

namespace App\Repository;

use App\Entity\CourseVideo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CourseVideo>
 */
class CourseVideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourseVideo::class);
    }

    /**
     * Trouve les vidéos d'un cours
     * @return CourseVideo[]
     */
    public function findByCourse(int $courseId): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.course = :courseId')
            ->setParameter('courseId', $courseId)
            ->orderBy('v.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les vidéos gratuites (preview) d'un cours
     * @return CourseVideo[]
     */
    public function findFreePreviewsByCourse(int $courseId): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.course = :courseId')
            ->andWhere('v.isFreePreview = :free')
            ->setParameter('courseId', $courseId)
            ->setParameter('free', true)
            ->orderBy('v.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

