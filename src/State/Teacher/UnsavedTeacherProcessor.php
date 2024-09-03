<?php

namespace App\State\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Teacher;
use App\Service\Teacher\TeacherManager;

class UnsavedTeacherProcessor implements ProcessorInterface
{
    public function __construct(private readonly TeacherManager $manager)
    {
    }

    /**
     * @throws \Exception
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Teacher
    {
        return $this->manager->unsaved($data);
    }
}