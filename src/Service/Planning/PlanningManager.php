<?php

namespace App\Service\Planning;

use App\Entity\Planning;
use App\Entity\User;
use App\Entity\UserTeacher;
use App\Entity\Course;
use App\Event\PlanningBookedEvent;
use App\Event\PlanningCreatedEvent;
use App\Exception\InsufficientHoursException;
use App\Exception\OverlappingBookingException;
use App\Repository\PlanningRepository;
use App\Service\Inbox\SalonThreadService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class PlanningManager
{
    public function __construct(
        private Security                 $security,
        private EventDispatcherInterface $dispatcher,
        private EntityManagerInterface   $em,
        private PlanningRepository       $planningRepository,
        private SalonThreadService       $salonThreads,
    )
    {
    }

    /**
     * @throws \Exception
     */
    public function create(Planning $data): Planning
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $this->em->beginTransaction();
        try {
            $this->validateBooking($data, $user);

            $data->addParticipant($user);

            $this->em->persist($data);

            $this->dispatcher->dispatch(new PlanningCreatedEvent($data));

            $this->em->flush();
            $this->salonThreads->findOrCreateForPlanning($data);
            $this->em->commit();

            return $data;
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    /**
     * Hours are deducted at booking time. Starting the live room only
     * flips the planning status — never debit again.
     *
     * @throws \Exception
     */
    public function start(int|string $planningId): Planning
    {
        $planning = is_numeric((string) $planningId)
            ? $this->planningRepository->find((int) $planningId)
            : $this->planningRepository->findOneBy(['meetingLink' => (string) $planningId]);

        if (!$planning) {
            throw new \Exception("Planning not found for $planningId");
        }

        $now = new \DateTimeImmutable('now');
        if ($planning->getEnd() && $planning->getEnd() <= $now->modify('-30 minutes')) {
            throw new \Exception("La date de fin du planning ({$planning->getEnd()->format('Y-m-d H:i:s')}) ne peut pas être inférieure à l'heure actuelle moins 30 minutes.");
        }

        if (!in_array($planning->getStatus(), [Planning::STATUS_CREATED, Planning::STATUS_STARTED, Planning::STATUS_PENDING], true)) {
            throw new \Exception("Cette séance ne peut pas être démarrée.");
        }

        $planning->setStatus(Planning::STATUS_STARTED);
        $this->em->persist($planning);
        $this->em->flush();

        return $planning;
    }

    /**
     * Teacher organizes a 1:1 or salon (group) live session.
     *
     * @param list<int> $studentIds
     */
    public function organize(\DateTimeImmutable $start, array $studentIds, ?\DateTimeImmutable $end = null, ?Course $course = null): Planning
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $teacher = $user?->getTeacher();
        if (!$teacher) {
            throw new AccessDeniedHttpException('Seul un professeur peut organiser une séance.');
        }

        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));
        if ($studentIds === []) {
            throw new BadRequestHttpException('Sélectionnez au moins un apprenant.');
        }

        $this->em->beginTransaction();
        try {
            $planning = (new Planning())
                ->setTeacher($teacher)
                ->setStart($start)
                ->setCourse($course)
                ->setStatus(Planning::STATUS_CREATED);

            $planning->setEnd($end ?: $start->modify('+50 minutes'));

            foreach ($studentIds as $studentId) {
                $student = $this->em->getRepository(User::class)->find($studentId);
                if (!$student instanceof User) {
                    throw new BadRequestHttpException("Apprenant #$studentId introuvable.");
                }

                $link = $this->em->getRepository(UserTeacher::class)->findOneBy([
                    'user' => $student,
                    'teacher' => $teacher,
                ]);
                $hours = $link ? $link->getHours() : 0;
                if ($hours < 1) {
                    $name = $student->getFullname() ?: $student->getEmail();
                    throw new InsufficientHoursException("$name n'a plus d'heures disponibles.");
                }

                $link->setHours($hours - 1);
                $this->em->persist($link);
                $planning->addParticipant($student);
            }

            foreach ($planning->getParticipants() as $student) {
                $this->checkOverlappingBookings($planning, $student);
            }
            $this->checkTeacherAvailability($planning);

            $this->em->persist($planning);
            $this->dispatcher->dispatch(new PlanningCreatedEvent($planning));
            $this->em->flush();
            $this->salonThreads->findOrCreateForPlanning($planning);
            $this->em->commit();

            return $planning;
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }


    /**
     * @throws \Exception
     */
    public function end(Planning $planning): Planning
    {
        if ($planning->getEnd() >= new \DateTimeImmutable())
            throw new \Exception("Date cannot be less than end date");

        if ($planning->getStatus() !== Planning::STATUS_STARTED)
            throw new \Exception("Planning is not started");

        $planning->setStatus(Planning::STATUS_FINISHED);

        $this->em->persist($planning);
        $this->em->flush();

        return $planning;
    }

    public function cancel(Planning $planning): Planning
    {
        $now = new \DateTimeImmutable('now');
        $limit = $planning->getStart()->modify('-1 hour');

        // Autoriser l'annulation uniquement jusqu'à 1h avant le début
        if ($now >= $limit) {
            throw new \Exception("Vous ne pouvez annuler qu'au moins 1 heure avant le début du cours.");
        }

        $planning->setStatus(Planning::STATUS_CANCELED);

        if (!$planning->isTrial()) {
            /** @var User $user */
            $user = $this->security->getUser();
            $userTeacher = $this->em->getRepository(UserTeacher::class)
                ->findOneBy(['user' => $user, 'teacher' => $planning->getTeacher()]);

            if ($userTeacher) {
                $userTeacher->setHours($userTeacher->getHours() + 1);
                $this->em->persist($userTeacher);
            }
        }

        $this->em->persist($planning);
        $this->em->flush();

        return $planning;
    }

    /**
     * @throws \Exception
     */
    private function validateBooking(Planning $data, User $user): void
    {
        $teacher = $data->getTeacher();
        $userTeacher = $this->em->getRepository(UserTeacher::class)->findOneBy(['user' => $user, 'teacher' => $teacher]);
        $canTrial = !$user->getPlannings()->exists(function ($key, Planning $planning) use ($teacher) {
            return $planning->getTeacher()->getId() === $teacher->getId();
        });
        $hours = $userTeacher ? $userTeacher->getHours() : 0;

        if (!$canTrial && $hours < 1)
            throw new InsufficientHoursException();

        if ($data->getEnd() === null)
            $data->setEnd($data->getStart()->modify($canTrial ? '+25 minutes' : '+50 minutes'));

        $time = $data->getStart()->diff($data->getEnd());

        if ($time->invert != 0)
            throw new \Exception("L'heure de fin ne peut pas être avant l'heure de début.");

        if ($time->days > 0 || $time->h > 5)
            throw new \Exception("Vous ne pouvez pas réserver plus de 5 heures de formation d'affilée.");

        if (!$canTrial && $time->h > $hours)
            throw new InsufficientHoursException();

        $this->checkOverlappingBookings($data, $user);
        $this->checkTeacherAvailability($data);

        // The first session with a teacher is a free trial: debiting an hour
        // here charged for it, while cancel() refuses to refund a trial — so
        // the student lost a prepaid hour on a session that costs nothing.
        if ($userTeacher && !$canTrial) {
            $userTeacher->setHours($hours - 1);
            $this->em->persist($userTeacher);
        }

        if ($canTrial)
            $data->setIsTrial(true);

        $data->setStatus(Planning::STATUS_CREATED);
    }

    /**
     * @throws \Exception
     */
    private function checkOverlappingBookings(Planning $data, User $user): void
    {
        $existingPlannings = $this->planningRepository->findByParticipant($data->getTeacher(), $user);

        foreach ($existingPlannings as $existingPlanning) {
            if ($this->isOverlapping($data->getStart(), $data->getEnd(), $existingPlanning->getStart(), $existingPlanning->getEnd()))
                throw new OverlappingBookingException();
        }
    }

    /**
     * @throws \Exception
     */
    private function checkTeacherAvailability(Planning $data): void
    {
        $availabilities = [];
        foreach ($data->getTeacher()->getDisponibilities() as $availability) {
            if ($availability->isIsActive()) {
                $availabilities[] = $availability;
            }
        }

        if ($availabilities === []) {
            return;
        }

        $weekday = strtolower($data->getStart()->format('l'));
        $startHm = $data->getStart()->format('H:i');
        $endHm = $data->getEnd()->format('H:i');

        foreach ($availabilities as $availability) {
            if (strtolower((string) $availability->getDay()) !== $weekday) {
                continue;
            }
            $avStart = substr((string) $availability->getStart(), 0, 5);
            $avEnd = substr((string) $availability->getEnd(), 0, 5);
            if ($startHm >= $avStart && $endHm <= $avEnd) {
                return;
            }
        }

        throw new \Exception("Le idiomaster n'est pas disponible à ce créneau.");
    }

    private function isOverlapping(?\DateTimeImmutable $start1, ?\DateTimeImmutable $end1, ?\DateTimeImmutable $start2, ?\DateTimeImmutable $end2): bool
    {
        if (!$start1 || !$end1 || !$start2 || !$end2)
            throw new InvalidArgumentException('Invalid dates provided for overlap check.');

        return $start1 < $end2 && $start2 < $end1;
    }

    /**
     * @throws \Exception
     */
    public function book(Planning $data): Planning
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $this->em->beginTransaction();
        try {
            $this->validateBooking($data, $user);

            $data->addParticipant($user);

            $this->em->persist($data);

            $this->dispatcher->dispatch(new PlanningBookedEvent($data));

            $this->em->flush();
            $this->salonThreads->findOrCreateForPlanning($data);
            $this->em->commit();

            return $data;
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }
}