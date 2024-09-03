<?php

namespace App\Repository;

use App\Entity\TeacherFormation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeacherFormation>
 *
 * @method TeacherFormation|null find($id, $lockMode = null, $lockVersion = null)
 * @method TeacherFormation|null findOneBy(array $criteria, array $orderBy = null)
 * @method TeacherFormation[]    findAll()
 * @method TeacherFormation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TeacherFormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeacherFormation::class);
    }

//    /**
//     * @return TeacherFormation[] Returns an array of TeacherFormation objects
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

//    public function findOneBySomeField($value): ?TeacherFormation
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
