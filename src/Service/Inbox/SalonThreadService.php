<?php

namespace App\Service\Inbox;

use App\Entity\InboxThread;
use App\Entity\Planning;
use App\Entity\User;
use App\Repository\InboxThreadRepository;
use Doctrine\ORM\EntityManagerInterface;

class SalonThreadService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InboxThreadRepository $threads,
    ) {
    }

    public function findOrCreateForPlanning(Planning $planning): InboxThread
    {
        $existing = $this->threads->findOneBy(['planning' => $planning]);
        if ($existing instanceof InboxThread) {
            $this->syncParticipants($planning, $existing);
            $this->em->flush();

            return $existing;
        }

        $thread = new InboxThread();
        $thread->setPlanning($planning);
        $thread->setTeacher($planning->getTeacher());
        $this->syncParticipants($planning, $thread);
        $planning->setSalonThread($thread);

        $this->em->persist($thread);
        $this->em->flush();

        return $thread;
    }

    private function syncParticipants(Planning $planning, InboxThread $thread): void
    {
        $teacherUser = $planning->getTeacher()?->getUser();
        if ($teacherUser instanceof User) {
            $thread->addParticipant($teacherUser);
        }

        foreach ($planning->getParticipants() as $participant) {
            $thread->addParticipant($participant);
        }
    }
}
