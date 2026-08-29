<?php

namespace App\State\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\InboxThreadRepository;
use Symfony\Bundle\SecurityBundle\Security;

readonly class TeacherInboxThreadProvider implements ProviderInterface
{
    public function __construct(
        private InboxThreadRepository $repository,
        private Security $security,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $threads = $user->getInboxThreads()->toArray();
        if ($user->getTeacher()) {
            $teacherThreads = $this->repository->findBy(['teacher' => $user->getTeacher()->getId()]);
            $threads = array_merge($threads, $teacherThreads);
        }

        $unique = [];
        foreach ($threads as $thread) {
            if ($thread->getId()) {
                $unique[$thread->getId()] = $thread;
            }
        }

        return array_values($unique);
    }
}
