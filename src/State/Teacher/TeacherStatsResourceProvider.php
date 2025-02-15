<?php

namespace App\State\Teacher;

use App\ApiResource\StatsResource;
use App\Entity\User;
use App\Model\StatResourceModel;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

readonly class TeacherStatsResourceProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User $user */
        $user = $this->security->getUser();

        if (is_null($user->getTeacher()))
            throw new AccessDeniedHttpException('You must be a teacher in to access this resource.');

        //$stats = $this->t->countDocumentsByStatus();


        return new StatsResource();
    }
}
