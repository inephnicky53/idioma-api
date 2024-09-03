<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Idioma;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 *
 * @method Transaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transaction[]    findAll()
 * @method Transaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

//    /**
//     * @return Transaction[] Returns an array of Transaction objects
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

//    public function findOneBySomeField($value): ?Transaction
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    public function findWaitingsResults()
    {
        $waiting = (new DateTime())->modify(Idioma::WAITING_TIME);

        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :val')
            ->andWhere('t.providerReference IS NOT NULL')
            ->andWhere('t.createdAt < :last')
            //->andWhere('t.is_sms_send = false')
            ->setParameter('last', $waiting, Types::DATETIME_MUTABLE)
            ->setParameter('val', Idioma::STATUS_WAIT)
            ->orderBy('t.id', 'ASC')
            //->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    public function findWaitingsAllResults($status = Idioma::STATUS_WAIT, $is_sms_send = 1, $max_result = null)
    {
        $waiting = (new DateTime())->modify(Idioma::WAITING_TIME);

        $req = $this->createQueryBuilder('t')
            ->andWhere('t.status = :val')
            ->andWhere('t.providerReference IS NOT NULL')
            ->andWhere('t.createdAt < :last')
            //->andWhere('t.is_sms_send = '. $is_sms_send)
            ->setParameter('last', $waiting, Types::DATETIME_MUTABLE)
            ->setParameter('val', $status)
            ->orderBy('t.id', 'ASC');
        if ($max_result)
            $req->setMaxResults($max_result);

        return $req
            ->getQuery()
            ->getResult();
    }
}
