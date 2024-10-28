<?php

namespace App\Service\Planning;

use App\Dto\BookPlanningInput;
use App\Entity\Planning;
use App\Entity\User;
use App\Entity\UserTeacher;
use App\Event\PlanningCreatedEvent;
//use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class PlanningManager
{
    public function __construct(
        private Security                 $security,
        private EventDispatcherInterface $dispatcher,
        private EntityManagerInterface   $em,
        //private NotificationService $notificationService,
    )
    {
    }

    public function create(Planning $data): Planning
    {
        /** @var User $user */
        $user = $this->security->getUser();
        if ($data->isTrial()) {
            $user?->getTeachers()->map(function (UserTeacher $userTeacher) use (&$data, &$canTrial) {
                if ($userTeacher->getTeacher()->getId() === $data->getTeacher()->getId())
                    if ($userTeacher->getBuyedAt() === null)
                        $canTrial = true;
            });

            if ($data->getEnd() === null)
                $data->setEnd($data->getStart()->modify('+25 minutes'));
        } else {
            $user->getTeachers()->map(function (UserTeacher $teacher) use (&$hours, $data) {
                if ($teacher->getTeacher() === $data->getTeacher())
                    $hours += $teacher->getHours();
            });

            if ($hours < 1)
                throw new \Exception("Vous n'avez pas d'heure pour ce professeur");

            if ($data->getEnd() === null)
                $data->setEnd($data->getStart()->modify('+50 minutes'));

            $time = $data->getStart()->diff($data->getEnd());

            if ($time->invert != 0)
                throw new \Exception("L'heure de fin ne doit pas être inférieure à l'heure du début");

            if ($time->days > 0 || $time->h > 5)
                throw new \Exception("Vous ne pouvez pas réserver plus de 5 heures de formation d'affilé");

            if ($time->h > $hours)
                throw new \Exception("Vous n'avez pas suffisamment d'heure pour ce planning, veuillez rajouter des heures");
        }

        $data->addParticipant($user);

        $this->em->persist($data);
        $this->em->flush();

        $this->dispatcher->dispatch(new PlanningCreatedEvent($data));

        return $data;
    }

    public function book(BookPlanningInput $dto): User
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $this->em->beginTransaction();
        try {
            foreach ($dto->plannings as $input) {
                $planning = $this->em->getRepository(Planning::class)->find($input->id);
                if (!$planning)
                    throw new \Exception("This planning doesn't exist");

                /** @var UserTeacher $userTeacher */
                $userTeacher = $user->getTeachers()->filter(fn(UserTeacher $userTeacher) => $userTeacher->getTeacher() === $planning->getTeacher())[0];
                if (!$userTeacher)
                    throw new \Exception("You don't have any hours for this teacher");

                if ($userTeacher->getHours() < 1)
                    throw new \Exception("You don't have enough available hours to book all selected plannings.");

                if (!$planning->isFree())
                    throw new \Exception("The planning with ID {$planning->getId()} is not available for booking.");

                if ($planning->getParticipants()->contains($user))
                    throw new \Exception("You have already booked the planning with ID {$planning->getId()}.");


                $userTeacher->setHours($userTeacher->getHours() - 1);
                $this->em->persist($userTeacher);

                $planning->addParticipant($user);
                $this->em->persist($planning);
            }

            $this->em->flush();
            $this->em->commit();

            //$this->notificationService->notifyUser($user, "Booking d'heure", 'Vous venez de booker une heure');

            return $user;
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }
}