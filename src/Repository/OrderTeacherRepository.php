<?php

namespace App\Repository;

use App\Entity\OrderTeacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderTeacher>
 *
 * @method OrderTeacher|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderTeacher|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrderTeacher[]    findAll()
 * @method OrderTeacher[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderTeacherRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderTeacher::class);
    }

//    /**
//     * @return OrderTeacher[] Returns an array of OrderTeacher objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('o')
//            ->andWhere('o.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('o.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?OrderTeacher
//    {
//        return $this->createQueryBuilder('o')
//            ->andWhere('o.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
