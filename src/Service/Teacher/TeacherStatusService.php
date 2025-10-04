<?php

namespace App\Service\Teacher;

use App\Entity\Teacher;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use App\Event\Teacher\TeacherStatusChangedEvent;

readonly class TeacherStatusService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private LoggerInterface $logger,
        private EventDispatcherInterface $eventDispatcher
    ) 
    {
    }

    public function activate(Teacher $teacher): bool
    {
        try {
            /** @var User $currentUser*/
            $currentUser = $this->security->getUser();
            
            $teacher->setIsActive(true)
                ->setActivatedAt(new \DateTimeImmutable())
                ->setActivatedBy($currentUser)
                ->setStatus(Teacher::STATUS_VALIDATED); // Ajout du statut

            $this->entityManager->flush();
            
            $this->logger->info('Teacher activated', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getUser()?->getEmail(),
                'activated_by' => $currentUser?->getId()
            ]);

            $this->eventDispatcher->dispatch(
                new TeacherStatusChangedEvent($teacher, 'activated', $currentUser)
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to activate teacher', [
                'teacher_id' => $teacher->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function deactivate(Teacher $teacher): bool
    {
        try {
            /** @var User $currentUser*/
            $currentUser = $this->security->getUser();
            
            $teacher->setIsActive(false)
                ->setActivatedAt(new \DateTimeImmutable()) // Correction: devrait être deactivatedAt
                ->setActivatedBy($currentUser)
                ->setStatus(Teacher::STATUS_DEACTIVATE); // Ajout du statut

            $this->entityManager->flush();
            
            $this->logger->info('Teacher deactivated', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getUser()?->getEmail(),
                'deactivated_by' => $currentUser?->getId()
            ]);

            $this->eventDispatcher->dispatch(
                new TeacherStatusChangedEvent($teacher, 'deactivated', $currentUser)
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to deactivate teacher', [
                'teacher_id' => $teacher->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function verify(Teacher $teacher): bool
    {
        try {
            /** @var User $currentUser*/
            $currentUser = $this->security->getUser();
            
            $teacher->setIsVerified(true)
                ->setVerifiedAt(new \DateTimeImmutable())
                ->setVerifiedBy($currentUser);

            $this->entityManager->flush();
            
            $this->logger->info('Teacher verified', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getUser()?->getEmail(),
                'verified_by' => $currentUser?->getId()
            ]);

            $this->eventDispatcher->dispatch(
                new TeacherStatusChangedEvent($teacher, 'verified', $currentUser)
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to verify teacher', [
                'teacher_id' => $teacher->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Rejette un idiomaster (désactive et dé-vérifie)
     */
    public function reject(Teacher $teacher): bool
    {
        try {
            /** @var User $currentUser*/
            $currentUser = $this->security->getUser();
            
            $teacher->setIsActive(false)
                ->setIsVerified(false)
                ->setActivatedAt(null)
                ->setActivatedBy(null)
                ->setVerifiedAt(null)
                ->setVerifiedBy(null)
                ->setStatus(Teacher::STATUS_WAITING);

            $this->entityManager->flush();
            
            $this->logger->info('Teacher rejected', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getUser()?->getEmail(),
                'rejected_by' => $currentUser?->getId()
            ]);

            $this->eventDispatcher->dispatch(
                new TeacherStatusChangedEvent($teacher, 'rejected', $currentUser)
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to reject teacher', [
                'teacher_id' => $teacher->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Bloque un idiomaster
     */
    public function block(Teacher $teacher): bool
    {
        try {
            /** @var User $currentUser*/
            $currentUser = $this->security->getUser();
            
            $teacher->setIsActive(false)
                ->setStatus(Teacher::STATUS_BLOCKED);

            $this->entityManager->flush();
            
            $this->logger->warning('Teacher blocked', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getUser()?->getEmail(),
                'blocked_by' => $currentUser?->getId()
            ]);

            $this->eventDispatcher->dispatch(
                new TeacherStatusChangedEvent($teacher, 'blocked', $currentUser)
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to block teacher', [
                'teacher_id' => $teacher->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Vérifie si un idiomaster peut être activé
     */
    public function canActivate(Teacher $teacher): bool
    {
        return !$teacher->isIsActive() && 
               $teacher->getUser() !== null &&
               $teacher->getStatus() !== Teacher::STATUS_BLOCKED;
    }

    /**
     * Vérifie si un idiomaster peut être vérifié
     */
    public function canVerify(Teacher $teacher): bool
    {
        return !$teacher->isIsVerified() && 
               $teacher->getUser() !== null;
    }

    /**
     * Obtient le statut actuel d'un idiomaster
     */
    public function getStatus(Teacher $teacher): string
    {
        if ($teacher->getStatus() === Teacher::STATUS_BLOCKED) {
            return 'blocked';
        }
        
        if (!$teacher->isIsActive() && !$teacher->isIsVerified()) {
            return 'waiting';
        }
        
        if ($teacher->isIsActive() && $teacher->isIsVerified()) {
            return 'active';
        }
        
        if ($teacher->isIsActive() && !$teacher->isIsVerified()) {
            return 'active_unverified';
        }
        
        return 'inactive';
    }

    public function getTeacherStats(Teacher $teacher): array
    {
        return [
            'total_courses' => $teacher->getCourses()->count(),
            'total_students' => $teacher->getStudents()->count(),
            'average_rating' => 0, //$teacher->getAverageRating(),
            'total_ratings' => $teacher->getRatings()->count(),
            'active_disponibilities' => $teacher->getDisponibilities()
                ->filter(fn($d) => $d->isIsActive())->count(),
            'status' => $this->getStatus($teacher),
            'is_active' => $teacher->isIsActive(),
            'is_verified' => $teacher->isIsVerified()
        ];
    }
}