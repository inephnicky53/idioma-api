<?php

namespace App\Repository;

use App\Entity\TranslationRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TranslationRequest>
 */
class TranslationRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TranslationRequest::class);
    }

    /**
     * Find all new translation requests
     */
    public function findNewRequests()
    {
        return $this->createQueryBuilder('tr')
            ->where('tr.status = :status')
            ->setParameter('status', 'new')
            ->orderBy('tr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find translation requests by status
     */
    public function findByStatus(string $status)
    {
        return $this->createQueryBuilder('tr')
            ->where('tr.status = :status')
            ->setParameter('status', $status)
            ->orderBy('tr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find translation requests by email
     */
    public function findByEmail(string $email)
    {
        return $this->createQueryBuilder('tr')
            ->where('tr.email = :email')
            ->setParameter('email', $email)
            ->orderBy('tr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

