<?php

namespace App\Repository;

use App\Entity\News;
use App\Enum\NewsStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NewsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, News::class);
    }

    /**
     * Trouve toutes les actualités publiées
     * @return News[]
     */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.status = :status')
            ->setParameter('status', NewsStatus::PUBLISHED)
            ->orderBy('n.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les actualités publiées avec pagination
     * @return News[]
     */
    public function findPublishedPaginated(int $limit = 10, int $offset = 0): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.status = :status')
            ->setParameter('status', NewsStatus::PUBLISHED)
            ->orderBy('n.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre d'actualités publiées
     */
    public function countPublished(): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.status = :status')
            ->setParameter('status', NewsStatus::PUBLISHED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les actualités par statut
     * @return News[]
     */
    public function findByStatus(NewsStatus $status): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.status = :status')
            ->setParameter('status', $status)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les dernières actualités publiées
     * @return News[]
     */
    public function findLatestPublished(int $limit = 5): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.status = :status')
            ->setParameter('status', NewsStatus::PUBLISHED)
            ->orderBy('n.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une actualité publiée par ID
     */
    public function findPublishedById(int $id): ?News
    {
        return $this->createQueryBuilder('n')
            ->where('n.id = :id')
            ->andWhere('n.status = :status')
            ->setParameter('id', $id)
            ->setParameter('status', NewsStatus::PUBLISHED)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compte les actualités par statut
     */
    public function countByStatus(NewsStatus $status): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les brouillons
     * @return News[]
     */
    public function findDrafts(): array
    {
        return $this->findByStatus(NewsStatus::DRAFT);
    }

    /**
     * Trouve les actualités archivées
     * @return News[]
     */
    public function findArchived(): array
    {
        return $this->findByStatus(NewsStatus::ARCHIVED);
    }

    /**
     * Recherche dans les actualités publiées
     * @return News[]
     */
    public function searchPublished(string $query): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.status = :status')
            ->andWhere('n.title LIKE :query OR n.excerpt LIKE :query OR n.content LIKE :query')
            ->setParameter('status', NewsStatus::PUBLISHED)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('n.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
