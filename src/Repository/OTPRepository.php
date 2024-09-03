<?php

namespace App\Repository;

use App\Entity\OTP;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OTP>
 *
 * @method OTP|null find($id, $lockMode = null, $lockVersion = null)
 * @method OTP|null findOneBy(array $criteria, array $orderBy = null)
 * @method OTP[]    findAll()
 * @method OTP[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OTPRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OTP::class);
    }

//    /**
//     * @return OTP[] Returns an array of OTP objects
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

//    public function findOneBySomeField($value): ?OTP
//    {
//        return $this->createQueryBuilder('o')
//            ->andWhere('o.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    public function add(OTP $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(OTP $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
    public function deleteBy(User $user, string $type)
    {
        $results = $this->createQueryBuilder('o')
            ->andWhere('o.user = :val')
            ->andWhere('o.type = :type')
            ->setParameter('val', $user)
            ->setParameter('type', $type)
            ->getQuery()
            ->getResult();
        foreach ($results as $result)
            $this->getEntityManager()->remove($result);
        $this->getEntityManager()->flush();
    }

    public function findUserAndPass(User $user, ?string $pass)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.pass = :pass')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user->getId())
            ->setParameter('pass', $pass)
            ->getQuery()
            ->getOneOrNullResult()
            ;
    }
}
