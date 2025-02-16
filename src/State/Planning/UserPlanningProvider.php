<?php

namespace App\State\Planning;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

readonly class UserPlanningProvider implements ProviderInterface
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

        $plannings = [];
        foreach ($user->getPlannings() as $planning) {
            $plannings[] = $planning;
        }
        if ($user->getTeacher()) {
            foreach ($user->getTeacher()->getPlannings() as $planning) {
                $plannings[] = $planning;
            }
        }

        return $plannings;
    }
}
