<?php

namespace App\State\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

readonly class TeacherCheckProvider implements ProviderInterface
{
    public function __construct(
        private Security $security
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User $user */
        $user = $this->security->getUser();
        if(!$teacher = $user->getTeacher())
            return new JsonResponse(['status' => false, "Vous n'êtes pas idiomaster"]);
        return $teacher;
    }
}
