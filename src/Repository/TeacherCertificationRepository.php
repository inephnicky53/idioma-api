<?php

namespace App\Repository;

use App\Entity\TeacherCertification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeacherCertification>
 *
 * @method TeacherCertification|null find($id, $lockMode = null, $lockVersion = null)
 * @method TeacherCertification|null findOneBy(array $criteria, array $orderBy = null)
 * @method TeacherCertification[]    findAll()
 * @method TeacherCertification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TeacherCertificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeacherCertification::class);
    }

//    /**
//     * @return TeacherCertification[] Returns an array of TeacherCertification objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?TeacherCertification
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
