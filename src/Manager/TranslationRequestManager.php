<?php

namespace App\Manager;

use App\Entity\TranslationRequest;
use App\Enum\TranslationStatus;
use App\Repository\TranslationRequestRepository;
use Doctrine\ORM\EntityManagerInterface;

class TranslationRequestManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslationRequestRepository $repository
    ) {
    }

    /**
     * Marque une demande comme en cours
     */
    public function markInProgress(TranslationRequest $request): void
    {
        $request->setStatus(TranslationStatus::IN_PROGRESS);
        $request->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }

    /**
     * Marque une demande comme terminée
     */
    public function markCompleted(TranslationRequest $request): void
    {
        $request->setStatus(TranslationStatus::COMPLETED);
        $request->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }

    /**
     * Annule une demande
     */
    public function cancel(TranslationRequest $request): void
    {
        $request->setStatus(TranslationStatus::CANCELLED);
        $request->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }

    /**
     * Crée une nouvelle demande
     */
    public function create(array $data): TranslationRequest
    {
        $request = new TranslationRequest();
        $request->setName($data['name']);
        $request->setEmail($data['email']);
        $request->setDocumentType($data['documentType']);
        $request->setSourceLanguage($data['sourceLanguage']);
        $request->setTargetLanguage($data['targetLanguage']);
        $request->setStatus(TranslationStatus::PENDING);
        
        if (isset($data['phone'])) {
            $request->setPhone($data['phone']);
        }
        
        if (isset($data['deadline'])) {
            $request->setDeadline($data['deadline']);
        }
        
        if (isset($data['message'])) {
            $request->setMessage($data['message']);
        }

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return $request;
    }

    /**
     * Met à jour une demande
     */
    public function update(TranslationRequest $request, array $data): TranslationRequest
    {
        if (isset($data['name'])) {
            $request->setName($data['name']);
        }
        
        if (isset($data['email'])) {
            $request->setEmail($data['email']);
        }
        
        if (isset($data['documentType'])) {
            $request->setDocumentType($data['documentType']);
        }
        
        if (isset($data['sourceLanguage'])) {
            $request->setSourceLanguage($data['sourceLanguage']);
        }
        
        if (isset($data['targetLanguage'])) {
            $request->setTargetLanguage($data['targetLanguage']);
        }
        
        if (isset($data['phone'])) {
            $request->setPhone($data['phone']);
        }
        
        if (isset($data['deadline'])) {
            $request->setDeadline($data['deadline']);
        }
        
        if (isset($data['message'])) {
            $request->setMessage($data['message']);
        }
        
        if (isset($data['status'])) {
            $request->setStatus($data['status']);
        }

        $request->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return $request;
    }

    /**
     * Supprime une demande
     */
    public function delete(TranslationRequest $request): void
    {
        $this->entityManager->remove($request);
        $this->entityManager->flush();
    }

    /**
     * Récupère les statistiques
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->repository->count([]),
            'pending' => $this->repository->count(['status' => TranslationStatus::PENDING]),
            'in_progress' => $this->repository->count(['status' => TranslationStatus::IN_PROGRESS]),
            'completed' => $this->repository->count(['status' => TranslationStatus::COMPLETED]),
            'cancelled' => $this->repository->count(['status' => TranslationStatus::CANCELLED]),
        ];
    }
}

