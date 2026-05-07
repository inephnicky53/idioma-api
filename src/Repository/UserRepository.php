<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $hashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($hashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findActiveUsers(): array
    {
        return $this->findBy(['isActive' => true]);
    }

    /**
     * Find users with active subscriptions for the current month
     */
    public function findUsersWithActiveSubscriptionsThisMonth(): array
    {
        $now = new \DateTime();
        $startOfMonth = (new \DateTime())->modify('first day of this month')->setTime(0, 0, 0);
        $endOfMonth = (new \DateTime())->modify('last day of this month')->setTime(23, 59, 59);

        return $this->createQueryBuilder('u')
            ->innerJoin('u.subscriptions', 's')
            ->where('s.status = :status')
            ->andWhere('s.startDate <= :now')
            ->andWhere('s.endDate >= :startOfMonth')
            ->setParameter('status', 'active')
            ->setParameter('now', $now)
            ->setParameter('startOfMonth', $startOfMonth)
            ->orderBy('u.email', 'ASC')
            ->distinct()
            ->getQuery()
            ->getResult();
    }

    public function getTotalUsersCount(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getActiveUsersCount(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getNewUsersCount(\DateTime $startDate, \DateTime $endDate): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :start')
            ->andWhere('u.createdAt <= :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getUsersWithActiveSubscriptionsCount(): int
    {
        $now = new \DateTime();
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(DISTINCT u.id)')
            ->innerJoin('u.subscriptions', 's')
            ->where('s.status = :status')
            ->andWhere('s.startDate <= :now')
            ->andWhere('s.endDate >= :now')
            ->setParameter('status', 'active')
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

