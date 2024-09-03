<?php

namespace App\Controller\Api\Planning;

use App\Entity\Planning;
use App\Entity\User;
use App\Entity\UserTeacher;
use App\Event\PlanningCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class PlanningCreateController extends AbstractController
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher
    )
    {
    }

    public function __invoke(Planning $data, EntityManagerInterface $entityManager): JsonResponse|Planning
    {
        /** @var User $user */
        $user = $this->getUser();
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
                return $this->json([
                    'status' => false,
                    'code' => 1,
                    'message' => "Vous n'avez pas d'heure pour ce professeur"
                ]);

            if ($data->getEnd() === null)
                $data->setEnd($data->getStart()->modify('+50 minutes'));

            $time = $data->getStart()->diff($data->getEnd());

            if ($time->invert != 0)
                return $this->json([
                    'status' => false,
                    'code' => 1,
                    'message' => "L'heure de fin ne doit pas être inférieure à l'heure du début"
                ]);

            if ($time->days > 0 || $time->h > 5)
                return $this->json([
                    'status' => false,
                    'code' => 2,
                    'message' => "Vous ne pouvez pas réserver plus de 5 heures de formation d'affilé"
                ]);

            if ($time->h > $hours)
                return $this->json([
                    'status' => false,
                    'code' => 3,
                    'message' => "Vous n'avez pas suffisamment d'heure pour ce planning, veuillez rajouter des heures"
                ]);
        }

        $data->addParticipant($user);

        $this->dispatcher->dispatch(new PlanningCreatedEvent($data));

        return $data;
    }
}
