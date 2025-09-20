<?php

namespace App\State\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\UpdateTeacherInput;
use App\Entity\Teacher;
use App\Service\Teacher\TeacherManager;
use Symfony\Bundle\SecurityBundle\Security;

readonly class UpdateTeacherProcessor implements ProcessorInterface
{
    public function __construct(
        private TeacherManager $teacherManager,
        private Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Teacher
    {
        return $this->teacherManager->update($data);
    }
}