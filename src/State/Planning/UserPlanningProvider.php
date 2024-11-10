<?php

namespace App\State\Planning;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

readonly class UserPlanningProvider implements ProviderInterface
{
    public function __construct(private Security $security)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $userPlanning = $user->getPlannings()->toArray();
        if ($teacher = $user->getTeacher())
            $userPlanning = array_merge($userPlanning, $teacher->getPlannings()->toArray());

        return $userPlanning;
    }
}
