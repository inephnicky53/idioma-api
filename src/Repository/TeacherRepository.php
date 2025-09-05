<?php

namespace App\Repository;

use App\Entity\Teacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Teacher>
 *
 * @method Teacher|null find($id, $lockMode = null, $lockVersion = null)
 * @method Teacher|null findOneBy(array $criteria, array $orderBy = null)
 * @method Teacher[]    findAll()
 * @method Teacher[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TeacherRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Teacher::class);
    }

    public function findActiveTeachers(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.isActive = :active')
            ->andWhere('t.isVerified = :verified')
            ->setParameter('active', true)
            ->setParameter('verified', true)
            ->leftJoin('t.user', 'u')
            ->addSelect('u')
            ->leftJoin('t.spokenLanguages', 'sl')
            ->addSelect('sl')
            ->leftJoin('t.teachingLanguages', 'tl')
            ->addSelect('tl')
            ->orderBy('t.createdAt', 'DESC');
    }

    public function findPendingTeachers(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.isActive = :active')
            ->andWhere('t.isVerified = :verified')
            ->setParameter('active', false)
            ->setParameter('verified', false)
            ->leftJoin('t.user', 'u')
            ->addSelect('u')
            ->orderBy('t.submitedAt', 'ASC');
    }

    public function findDeactivatedTeachers(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.isActive = :active')
            ->setParameter('active', false)
            ->leftJoin('t.user', 'u')
            ->addSelect('u')
            ->orderBy('t.activatedAt', 'DESC');
    }

    public function getTeacherStats(): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id) as total')
            ->addSelect('SUM(CASE WHEN t.isActive = true THEN 1 ELSE 0 END) as active')
            ->addSelect('SUM(CASE WHEN t.isVerified = true THEN 1 ELSE 0 END) as verified')
            ->addSelect('SUM(CASE WHEN t.isActive = false AND t.isVerified = false THEN 1 ELSE 0 END) as pending');

        return $qb->getQuery()->getSingleResult();
    }

    public function findTeachersWithAvailabilities(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.disponibilities', 'd')
            ->andWhere('d.isActive = :active')
            ->andWhere('t.isActive = :teacherActive')
            ->setParameter('active', true)
            ->setParameter('teacherActive', true)
            ->leftJoin('t.user', 'u')
            ->addSelect('u', 'd')
            ->orderBy('t.price', 'ASC');
    }
}
