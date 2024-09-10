<?php

namespace App\State\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Rating;
use App\Entity\Teacher;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class TeacherGetCommentProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private readonly ProviderInterface $inner,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var Teacher $teacher */
        $teacher = $this->inner->provide($operation, $uriVariables, $context);


        return $teacher->getRatings()->toArray();
    }
}