<?php

namespace App\State\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\DisponibilityOutput;
use App\Entity\Disponibility;
use App\Entity\Planning;
use App\Entity\Teacher;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

readonly class TeacherDisponibilitiesAdvancedProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $teacherRepository = $this->entityManager->getRepository(Teacher::class);
        $teacher = $teacherRepository->find($uriVariables['id']);

        if (!$teacher) {
            return [];
        }

        $disponibilities = [];
        
        foreach ($teacher->getDisponibilities() as $disponibility) {
            $isActive = $this->isDisponibilityActive($teacher, $disponibility);
            
            $disponibilities[] = new DisponibilityOutput(
                $disponibility->getId(),
                $disponibility->getDay(),
                $disponibility->getStart(),
                $disponibility->getEnd(),
                $isActive
            );
        }

        return $disponibilities;
    }

    private function isDisponibilityActive(Teacher $teacher, Disponibility $disponibility): bool
    {
        // Si la disponibilité est désactivée dans l'entité, elle n'est pas active
        if (!$disponibility->isIsActive()) {
            return false;
        }

        // Vérifier s'il y a des plannings confirmés qui chevauchent avec cette disponibilité
        $now = new DateTimeImmutable();
        
        // Récupérer les plannings du professeur qui ne sont pas annulés ou rejetés
        $plannings = $this->entityManager->getRepository(Planning::class)
            ->createQueryBuilder('p')
            ->where('p.teacher = :teacher')
            ->andWhere('p.status NOT IN (:excludedStatuses)')
            ->andWhere('p.start >= :now')
            ->setParameter('teacher', $teacher)
            ->setParameter('excludedStatuses', [Planning::STATUS_CANCELED, Planning::STATUS_REJECTED])
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        // Vérifier s'il y a des conflits avec les plannings existants
        foreach ($plannings as $planning) {
            if ($this->hasTimeConflict($disponibility, $planning)) {
                return false;
            }
        }

        return true;
    }

    private function hasTimeConflict(Disponibility $disponibility, Planning $planning): bool
    {
        // Convertir le jour de la disponibilité en format numérique (1 = lundi, 7 = dimanche)
        $dayMapping = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7
        ];

        $disponibilityDay = $dayMapping[strtolower($disponibility->getDay())] ?? null;
        if (!$disponibilityDay) {
            return false;
        }

        // Vérifier si le planning est le même jour de la semaine
        $planningDay = (int) $planning->getStart()->format('N');
        if ($disponibilityDay !== $planningDay) {
            return false;
        }

        // Vérifier le chevauchement des heures
        $disponibilityStart = $disponibility->getStart();
        $disponibilityEnd = $disponibility->getEnd();
        $planningStart = $planning->getStart()->format('H:i');
        $planningEnd = $planning->getEnd()->format('H:i');

        // Vérifier s'il y a un chevauchement
        return !($disponibilityEnd <= $planningStart || $disponibilityStart >= $planningEnd);
    }
}