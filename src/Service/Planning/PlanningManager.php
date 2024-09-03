<?php

namespace App\Service\Planning;

use App\Entity\Planning;
use App\Entity\User;
use App\Entity\UserTeacher;
use App\Event\PlanningCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;

class PlanningManager
{
    public function __construct(
        private readonly Security                 $security,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly EntityManagerInterface   $em
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
}