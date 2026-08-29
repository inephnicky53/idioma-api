<?php

namespace App\State\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\UserTeacherRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

readonly class TeacherStudentsProvider implements ProviderInterface
{
    public function __construct(
        private Security              $security,
        private UserTeacherRepository $userTeacherRepository
    )
    {
    }

    /**
     * @throws AccessDeniedHttpException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?array
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        if (null === $user || null === ($teacher = $user->getTeacher())) {
            throw new AccessDeniedHttpException('You must be a teacher to access this resource.');
        }

        return $this->userTeacherRepository->createQueryBuilder('ut')
            ->andWhere('ut.teacher = :teacher')
            ->setParameter('teacher', $teacher)
            ->orderBy('ut.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}