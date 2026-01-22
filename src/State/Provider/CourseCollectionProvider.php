<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\CourseRepository;

class CourseCollectionProvider implements ProviderInterface
{
    public function __construct(private CourseRepository $courseRepository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Retourner seulement les cours publiés, triés par position
        return $this->courseRepository->findPublished();
    }
}

