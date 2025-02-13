<?php

namespace App\Service\Planning;

use App\Entity\Disponibility;
use App\Entity\Planning;
use App\Entity\User;
use App\Entity\UserTeacher;
use App\Event\PlanningBookedEvent;
use App\Event\PlanningCreatedEvent;
use App\Exception\InsufficientHoursException;
use App\Exception\OverlappingBookingException;
use App\Repository\PlanningRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class PlanningManager
{
    public function __construct(
        private Security                 $security,
        private EventDispatcherInterface $dispatcher,
        private EntityManagerInterface   $em,
        private PlanningRepository       $planningRepository
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
            $this->em->commit();

            return $data;
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    /**
     * @throws \Exception
     */
    public function start(Planning $planning): Planning
    {
        $now = new \DateTimeImmutable('now');

        if ($planning->getEnd() <= $now->modify('-30 minutes')) {
            throw new \Exception("La date de fin du planning ({$planning->getEnd()->format('Y-m-d H:i:s')}) ne peut pas être inférieure à l'heure actuelle moins 30 minutes.");
        }

        $planning->setStatus(Planning::STATUS_STARTED);

        foreach ($planning->getParticipants() as $participant) {
            $userTeacher = $this->em->getRepository(UserTeacher::class)->findOneBy([
                'teacher' => $participant->getTeacher()->getId(),
                'user' => $this->security->getUser()
            ]);

            if (!$userTeacher)
                throw new \Exception("Impossible de trouver l'association UserTeacher pour l'utilisateur et l'enseignant ID {$participant->getTeacher()->getId()}.");

            $newHours = $userTeacher->getHours() - 1;
            if ($newHours < 0)
                throw new \Exception("L'utilisateur n'a pas assez d'heures disponibles.");

            $userTeacher->setHours($newHours);
            $this->em->persist($userTeacher);
        }

        $this->em->persist($planning);
        $this->em->flush();

        return $planning;
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
        if ($planning->getStart() >= new \DateTimeImmutable())
            throw new \Exception("Date cannot be less than start date");

        $planning->setStatus(Planning::STATUS_CANCELED);

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
        $isTrial = $user->getPlannings()->exists(function ($key, Planning $planning) use ($teacher) {
            return $planning->getTeacher()->getId() === $teacher->getId();
        });
        $hours = $userTeacher ? $userTeacher->getHours() : 0;

        if (!$isTrial && $hours < 1)
            throw new InsufficientHoursException();

        if ($data->getEnd() === null)
            $data->setEnd($data->getStart()->modify($isTrial ? '+25 minutes' : '+50 minutes'));

        $time = $data->getStart()->diff($data->getEnd());

        if ($time->invert != 0)
            throw new \Exception("L'heure de fin ne peut pas être avant l'heure de début.");

        if ($time->days > 0 || $time->h > 5)
            throw new \Exception("Vous ne pouvez pas réserver plus de 5 heures de formation d'affilée.");

        if (!$isTrial && $time->h > $hours)
            throw new InsufficientHoursException();

        $this->checkOverlappingBookings($data, $user);
        $this->checkTeacherAvailability($data);

        if ($userTeacher) {
            $userTeacher->setHours($hours - 1);
            $this->em->persist($userTeacher);
        }

        if ($isTrial)
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
        $teacherAvailabilities = $data->getTeacher()->getDisponibilities()->toArray();
        $currentYear = date('Y');

        /** @var Disponibility $availability */
        foreach ($teacherAvailabilities as $availability) {
            $day = $availability->getDay();
            $start = \DateTimeImmutable::createFromFormat('Y-m-d H:i', "$currentYear-" . date('W', strtotime($day)) . '-1 ' . $availability->getStart());
            $end = \DateTimeImmutable::createFromFormat('Y-m-d H:i', "$currentYear-" . date('W', strtotime($day)) . '-1 ' . $availability->getEnd());

            if ($availability->isIsActive() && $this->isOverlapping($data->getStart(), $data->getEnd(), $start, $end))
                throw new \Exception("Le professeur n'est pas disponible à ce créneau.");
        }
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
            $this->em->commit();

            return $data;
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }
}