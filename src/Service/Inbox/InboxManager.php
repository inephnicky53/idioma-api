<?php

namespace App\Service\Inbox;

use App\Entity\InboxMessage;
use App\Entity\InboxThread;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class InboxManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security               $security,
    )
    {
    }

    /**
     * @throws \Exception
     */
    public function createThread(InboxThread $data): InboxThread
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $teacher = $data->getTeacher();

        if (!$teacher) {
            throw new \InvalidArgumentException('Teacher is required');
        }

        $teacherUser = $teacher->getUser();
        $isTeacherInitiator = $teacherUser && $user === $teacherUser;

        if ($isTeacherInitiator) {
            $student = null;
            foreach ($data->getParticipants() as $participant) {
                if ($participant !== $user) {
                    $student = $participant;
                    break;
                }
            }

            if (!$student instanceof User) {
                throw new \InvalidArgumentException('A student participant is required');
            }

            foreach ($this->em->getRepository(InboxThread::class)->findBy(['teacher' => $teacher]) as $existing) {
                if ($existing->getParticipants()->contains($student)) {
                    return $existing;
                }
            }

            if (!$data->getParticipants()->contains($user)) {
                $data->addParticipant($user);
            }
        } else {
            if ($user === $teacherUser) {
                throw new \InvalidArgumentException("You can't create a thread with yourself");
            }

            foreach ($user->getInboxThreads() as $existing) {
                if ($existing->getTeacher()?->getId() === $teacher->getId()) {
                    return $existing;
                }
            }

            $data->addParticipant($user);
            if ($teacherUser) {
                $data->addParticipant($teacherUser);
            }
        }

        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }

    public function save(InboxMessage $data)
    {
    }
}