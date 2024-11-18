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

        if ($user === $data->getTeacher()->getUser())
            throw new \Exception("You can't create a thread to yourself");

        $thread = $this->em->getRepository(InboxThread::class)->findOneBy(['teacher' => $data->getTeacher()]);
        if ($thread)
            throw new \Exception("You already have a thread with this teacher");

        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }

    public function save(InboxMessage $data)
    {
    }
}