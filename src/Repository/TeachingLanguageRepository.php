<?php

namespace App\Repository;

use App\Entity\TeachingLanguage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeachingLanguage>
 *
 * @method TeachingLanguage|null find($id, $lockMode = null, $lockVersion = null)
 * @method TeachingLanguage|null findOneBy(array $criteria, array $orderBy = null)
 * @method TeachingLanguage[]    findAll()
 * @method TeachingLanguage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TeachingLanguageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeachingLanguage::class);
    }

//    /**
//     * @return TeachingLanguage[] Returns an array of TeachingLanguage objects
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

//    public function findOneBySomeField($value): ?TeachingLanguage
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
