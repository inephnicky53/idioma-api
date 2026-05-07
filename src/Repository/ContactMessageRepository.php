<?php

namespace App\Repository;

use App\Entity\ContactMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactMessage>
 */
class ContactMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactMessage::class);
    }

    /**
     * Find all new messages
     */
    public function findNewMessages()
    {
        return $this->createQueryBuilder('cm')
            ->where('cm.status = :status')
            ->setParameter('status', 'new')
            ->orderBy('cm.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find messages by status
     */
    public function findByStatus(string $status)
    {
        return $this->createQueryBuilder('cm')
            ->where('cm.status = :status')
            ->setParameter('status', $status)
            ->orderBy('cm.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find messages by email
     */
    public function findByEmail(string $email)
    {
        return $this->createQueryBuilder('cm')
            ->where('cm.email = :email')
            ->setParameter('email', $email)
            ->orderBy('cm.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

