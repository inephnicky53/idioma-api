<?php

namespace App\State\Inbox;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\InboxMessage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

readonly class SendInboxMessageProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InboxMessage
    {
        if (!$data instanceof InboxMessage) {
            throw new \InvalidArgumentException('Expected InboxMessage');
        }

        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $thread = $data->getThread();
        if (!$thread) {
            throw new \InvalidArgumentException('Thread is required');
        }

        if (!$this->canAccessThread($user, $thread)) {
            throw new AccessDeniedHttpException('You are not a participant of this conversation');
        }

        $data->setAuthor($user);
        $thread->addMessage($data);
        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }

    private function canAccessThread(User $user, \App\Entity\InboxThread $thread): bool
    {
        if ($thread->getParticipants()->contains($user)) {
            return true;
        }

        $teacher = $thread->getTeacher();
        return $teacher && $teacher->getUser() === $user;
    }
}
