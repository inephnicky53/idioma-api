<?php

namespace App\Service\Planning;

use App\Entity\Planning;
use App\Entity\PlanningAttendance;
use App\Entity\User;
use App\Entity\UserTeacher;
use App\Repository\PlanningAttendanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PlanningAttendanceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PlanningAttendanceRepository $attendances,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function roster(Planning $planning, User $viewer, bool $isHost): array
    {
        $records = [];
        foreach ($this->attendances->findForPlanning($planning) as $row) {
            $userId = $row->getStudent()?->getId();
            if ($userId) {
                $records[$userId] = $row;
            }
        }

        $out = [];
        $teacherUser = $planning->getTeacher()?->getUser();
        if ($teacherUser) {
            if ($isHost || $viewer->getId() === $teacherUser->getId() || $this->isParticipant($planning, $viewer)) {
                $row = $records[$teacherUser->getId()] ?? null;
                $out[] = $this->serialize($teacherUser, $row, $isHost, PlanningAttendance::PARTY_TEACHER);
            }
        }

        foreach ($planning->getParticipants() as $student) {
            if (!$isHost && $student->getId() !== $viewer->getId()) {
                continue;
            }

            $row = $records[$student->getId()] ?? null;
            $out[] = $this->serialize($student, $row, $isHost, PlanningAttendance::PARTY_STUDENT);
        }

        return $out;
    }

    /**
     * Teacher marks a student, or a student reports the teacher no-show.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function report(Planning $planning, User $reporter, array $payload): array
    {
        $teacherUser = $planning->getTeacher()?->getUser();
        $isHost = $teacherUser && $teacherUser->getId() === $reporter->getId();
        $targetId = (int) ($payload['userId'] ?? $payload['studentId'] ?? 0);

        $target = $this->resolveTarget($planning, $targetId);
        if (!$target instanceof User) {
            throw new BadRequestHttpException('Cette personne ne fait pas partie de la séance.');
        }

        $party = ($teacherUser && $target->getId() === $teacherUser->getId())
            ? PlanningAttendance::PARTY_TEACHER
            : PlanningAttendance::PARTY_STUDENT;

        if ($isHost && $party === PlanningAttendance::PARTY_TEACHER) {
            throw new BadRequestHttpException('Le professeur ne peut pas s\'auto-déclarer absent.');
        }

        if (!$isHost) {
            if ($party !== PlanningAttendance::PARTY_TEACHER) {
                throw new AccessDeniedHttpException('Seul le professeur peut faire l\'appel des apprenants.');
            }
            if (!$this->isParticipant($planning, $reporter)) {
                throw new AccessDeniedHttpException('Accès refusé.');
            }
            $this->assertSessionStarted($planning);
        }

        $status = (string) ($payload['status'] ?? PlanningAttendance::STATUS_PRESENT);
        if (!in_array($status, PlanningAttendance::statuses(), true)) {
            throw new BadRequestHttpException('Statut de présence invalide.');
        }

        $sanction = (string) ($payload['sanction'] ?? PlanningAttendance::SANCTION_NONE);
        if (!in_array($sanction, PlanningAttendance::sanctions(), true)) {
            throw new BadRequestHttpException('Sanction invalide.');
        }

        if ($party === PlanningAttendance::PARTY_TEACHER && $sanction === PlanningAttendance::SANCTION_DEDUCT_HOUR) {
            throw new BadRequestHttpException('On ne déduit pas d\'heure au professeur. Utilisez le remboursement.');
        }

        if ($party === PlanningAttendance::PARTY_STUDENT && $sanction === PlanningAttendance::SANCTION_REFUND_HOUR) {
            throw new BadRequestHttpException('Le remboursement s\'applique uniquement si le professeur est absent.');
        }

        if ($status !== PlanningAttendance::STATUS_ABSENT && in_array($sanction, [
            PlanningAttendance::SANCTION_DEDUCT_HOUR,
            PlanningAttendance::SANCTION_REFUND_HOUR,
        ], true)) {
            throw new BadRequestHttpException('Une heure ne peut être déduite ou remboursée que pour une absence.');
        }

        if (!$isHost && $status === PlanningAttendance::STATUS_ABSENT && $sanction === PlanningAttendance::SANCTION_NONE) {
            $sanction = PlanningAttendance::SANCTION_REFUND_HOUR;
        }

        $row = $this->attendances->findOneFor($planning, $target);
        if (!$row) {
            $row = (new PlanningAttendance())
                ->setPlanning($planning)
                ->setStudent($target)
                ->setParty($party);
            $this->em->persist($row);
        }

        $row->setParty($party);
        $row->setReportedBy($reporter);
        $row->setReportedAt(new \DateTimeImmutable());
        $row->setStatus($status);
        $row->setNote(isset($payload['note']) ? (string) $payload['note'] : $row->getNote());
        $row->setSanction($sanction);

        if ($sanction === PlanningAttendance::SANCTION_DEDUCT_HOUR && $row->getHoursDeducted() <= 0) {
            $this->applyHourDeduction($planning, $target, $row);
        }

        if ($sanction === PlanningAttendance::SANCTION_REFUND_HOUR && $row->getHoursRefunded() <= 0) {
            $this->applyTeacherNoShowRefund($planning, $row);
        }

        if ($sanction !== PlanningAttendance::SANCTION_NONE && !$row->getSanctionedAt()) {
            $row->setSanctionedAt(new \DateTimeImmutable());
        }

        $this->em->flush();

        return $this->serialize($target, $row, $isHost, $party);
    }

    public function assertHost(Planning $planning, User $user): void
    {
        if ($planning->getTeacher()?->getUser() !== $user) {
            throw new AccessDeniedHttpException('Seul le professeur peut faire l\'appel.');
        }
    }

    private function resolveTarget(Planning $planning, int $targetId): ?User
    {
        $teacherUser = $planning->getTeacher()?->getUser();
        if ($teacherUser && $teacherUser->getId() === $targetId) {
            return $teacherUser;
        }

        foreach ($planning->getParticipants() as $participant) {
            if ($participant->getId() === $targetId) {
                return $participant;
            }
        }

        return null;
    }

    private function isParticipant(Planning $planning, User $user): bool
    {
        foreach ($planning->getParticipants() as $participant) {
            if ($participant->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }

    private function assertSessionStarted(Planning $planning): void
    {
        $start = $planning->getStart();
        if (!$start) {
            throw new BadRequestHttpException('Séance sans horaire.');
        }

        $grace = $start->modify('+10 minutes');
        if (new \DateTimeImmutable() < $grace) {
            throw new BadRequestHttpException('Vous pourrez signaler l\'absence du professeur 10 minutes après le début.');
        }
    }

    private function applyHourDeduction(Planning $planning, User $student, PlanningAttendance $row): void
    {
        $link = $this->findLink($planning, $student);
        if (!$link) {
            $row->setHoursDeducted(0);

            return;
        }

        $hours = max(0, $link->getHours() - 1);
        $link->setHours($hours);
        $row->setHoursDeducted(1);
        $row->setSanctionedAt(new \DateTimeImmutable());
    }

    /**
     * Teacher no-show: give every booked student their hour back (once).
     */
    private function applyTeacherNoShowRefund(Planning $planning, PlanningAttendance $teacherRow): void
    {
        $refunded = 0;
        foreach ($planning->getParticipants() as $student) {
            $link = $this->findLink($planning, $student);
            if (!$link) {
                continue;
            }
            $link->addHours(1);
            $refunded++;
        }

        if ($planning->isTrial()) {
            $refunded = max($refunded, 1);
        }

        $teacherRow->setHoursRefunded($refunded > 0 ? 1 : 0);
        $teacherRow->setSanctionedAt(new \DateTimeImmutable());
    }

    private function findLink(Planning $planning, User $student): ?UserTeacher
    {
        $teacher = $planning->getTeacher();
        if (!$teacher) {
            return null;
        }

        return $this->em->getRepository(UserTeacher::class)->findOneBy([
            'user' => $student,
            'teacher' => $teacher,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(User $person, ?PlanningAttendance $row, bool $isHost, string $party): array
    {
        $data = [
            'userId' => $person->getId(),
            'name' => $person->getFullname() ?: $person->getEmail(),
            'role' => $party,
            'status' => $row?->getStatus(),
            'sanction' => $row?->getSanction() ?: PlanningAttendance::SANCTION_NONE,
            'note' => $isHost ? $row?->getNote() : null,
            'reportedAt' => $row?->getReportedAt()?->format(\DateTimeInterface::ATOM),
            'hoursDeducted' => $row?->getHoursDeducted() ?: 0,
            'hoursRefunded' => $row?->getHoursRefunded() ?: 0,
        ];

        if (!$isHost) {
            unset($data['note']);
        }

        return $data;
    }
}
