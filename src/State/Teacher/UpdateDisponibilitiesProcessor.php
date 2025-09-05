<?php

namespace App\State\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\UpdateDisponibilitiesInput;
use App\Entity\Teacher;
use App\Entity\User;
use App\Service\Teacher\TeacherManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class UpdateDisponibilitiesProcessor implements ProcessorInterface
{
    public function __construct(
        private Security       $security,
        private TeacherManager $teacherManager
    )
    {
    }

    /**
     * @param UpdateDisponibilitiesInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Teacher
    {
        /** @var User $user */
        $user = $this->security->getUser();
        if (!$user || !($teacher = $user->getTeacher())) {
            throw new AccessDeniedHttpException('You must be a teacher to update availabilities.');
        }

        return $this->teacherManager->updateAvailabilities($teacher, $data->availabilities);
    }
}