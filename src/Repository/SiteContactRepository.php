<?php

namespace App\Repository;

use App\Entity\SiteContact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteContact>
 */
class SiteContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteContact::class);
    }

    /**
     * @return SiteContact[]
     */
    public function findActiveForSite(string $site): array
    {
        $rows = $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->andWhere('(c.site = :both OR c.site = :site)')
            ->setParameter('active', true)
            ->setParameter('both', SiteContact::SITE_BOTH)
            ->setParameter('site', $site)
            ->getQuery()
            ->getResult();

        usort($rows, static function (SiteContact $a, SiteContact $b) use ($site) {
            $score = static fn (SiteContact $c) => $c->getSite() === $site ? 0 : 1;

            return $score($a) <=> $score($b);
        });

        return $rows;
    }
}
