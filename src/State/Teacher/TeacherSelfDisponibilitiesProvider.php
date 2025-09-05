<?php

namespace App\State\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class TeacherSelfDisponibilitiesProvider implements ProviderInterface
{
    public function __construct(private Security $security)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        if (!$user || !($teacher = $user->getTeacher())) {
            throw new AccessDeniedHttpException('You must be a teacher to access this resource.');
        }

        return $teacher;
    }
}