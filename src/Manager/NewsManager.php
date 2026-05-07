<?php

namespace App\Manager;

use App\Entity\News;
use App\Enum\NewsStatus;
use App\Repository\NewsRepository;
use Doctrine\ORM\EntityManagerInterface;

class NewsManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NewsRepository $newsRepository
    ) {
    }

    /**
     * Publie une actualité
     */
    public function publish(News $news): void
    {
        $news->setStatus(NewsStatus::PUBLISHED);
        
        if (!$news->getPublishedAt()) {
            $news->setPublishedAt(new \DateTime());
        }

        $this->entityManager->flush();
    }

    /**
     * Archive une actualité
     */
    public function archive(News $news): void
    {
        $news->setStatus(NewsStatus::ARCHIVED);
        $this->entityManager->flush();
    }

    /**
     * Met en brouillon une actualité
     */
    public function draft(News $news): void
    {
        $news->setStatus(NewsStatus::DRAFT);
        $this->entityManager->flush();
    }

    /**
     * Crée une nouvelle actualité
     */
    public function create(array $data): News
    {
        $news = new News();
        $news->setTitle($data['title']);
        $news->setContent($data['content']);
        
        if (isset($data['excerpt'])) {
            $news->setExcerpt($data['excerpt']);
        }
        
        if (isset($data['image'])) {
            $news->setImage($data['image']);
        }
        
        if (isset($data['status'])) {
            $news->setStatus($data['status']);
        }

        $this->entityManager->persist($news);
        $this->entityManager->flush();

        return $news;
    }

    /**
     * Met à jour une actualité
     */
    public function update(News $news, array $data): News
    {
        if (isset($data['title'])) {
            $news->setTitle($data['title']);
        }
        
        if (isset($data['content'])) {
            $news->setContent($data['content']);
        }
        
        if (isset($data['excerpt'])) {
            $news->setExcerpt($data['excerpt']);
        }
        
        if (isset($data['image'])) {
            $news->setImage($data['image']);
        }
        
        if (isset($data['status'])) {
            $news->setStatus($data['status']);
        }

        $this->entityManager->flush();

        return $news;
    }

    /**
     * Supprime une actualité
     */
    public function delete(News $news): void
    {
        $this->entityManager->remove($news);
        $this->entityManager->flush();
    }

    /**
     * Récupère les statistiques
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->newsRepository->count([]),
            'published' => $this->newsRepository->countByStatus(NewsStatus::PUBLISHED),
            'draft' => $this->newsRepository->countByStatus(NewsStatus::DRAFT),
            'archived' => $this->newsRepository->countByStatus(NewsStatus::ARCHIVED),
        ];
    }
}