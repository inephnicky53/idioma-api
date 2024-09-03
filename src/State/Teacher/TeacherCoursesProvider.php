<?php

namespace App\State\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\ArticleRepository;

class TeacherCoursesProvider implements ProviderInterface
{
    public function __construct()
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return dd($operation, $uriVariables, $context);
    }
}
