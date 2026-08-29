<?php

namespace App\Repository;

use App\Entity\SiteSocial;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteSocial>
 */
class SiteSocialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteSocial::class);
    }

    /**
     * @return SiteSocial[]
     */
    public function findActiveForSite(string $site): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.isActive = :active')
            ->andWhere('(s.site = :both OR s.site = :site)')
            ->setParameter('active', true)
            ->setParameter('both', SiteSocial::SITE_BOTH)
            ->setParameter('site', $site)
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
