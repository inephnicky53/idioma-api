<?php

namespace App\Repository;

use App\Entity\SpokenLanguage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpokenLanguage>
 *
 * @method SpokenLanguage|null find($id, $lockMode = null, $lockVersion = null)
 * @method SpokenLanguage|null findOneBy(array $criteria, array $orderBy = null)
 * @method SpokenLanguage[]    findAll()
 * @method SpokenLanguage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SpokenLanguageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpokenLanguage::class);
    }

//    /**
//     * @return TeacherLanguage[] Returns an array of TeacherLanguage objects
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

//    public function findOneBySomeField($value): ?TeacherLanguage
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
