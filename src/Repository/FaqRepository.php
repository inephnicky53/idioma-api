<?php

namespace App\Repository;

use App\Entity\Faq;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Faq>
 */
class FaqRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Faq::class);
    }

    /**
     * @return Faq[]
     */
    public function findActiveForSite(string $site): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.isActive = :active')
            ->andWhere('(f.site = :both OR f.site = :site)')
            ->setParameter('active', true)
            ->setParameter('both', Faq::SITE_BOTH)
            ->setParameter('site', $site)
            ->orderBy('f.position', 'ASC')
            ->addOrderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
