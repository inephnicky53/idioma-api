<?php

namespace App\Repository;

use App\Entity\InboxThread;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InboxThread>
 *
 * @method InboxThread|null find($id, $lockMode = null, $lockVersion = null)
 * @method InboxThread|null findOneBy(array $criteria, array $orderBy = null)
 * @method InboxThread[]    findAll()
 * @method InboxThread[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InboxThreadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InboxThread::class);
    }

//    /**
//     * @return InboxThread[] Returns an array of InboxThread objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('i.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?InboxThread
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
