<?php

namespace App\State\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Rating;
use App\Entity\User;
use App\Repository\RatingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

readonly class TeacherRatingProcessor implements ProcessorInterface
{
    public function __construct(
        private RatingRepository $ratingRepository,
        private EntityManagerInterface $em,
        private Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Rating
    {
        if (!$data instanceof Rating) {
            throw new \InvalidArgumentException('Expected Rating');
        }

        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $stars = $data->getStars();
        if ($stars === null || $stars < 1 || $stars > 5) {
            throw new BadRequestHttpException('La note doit être comprise entre 1 et 5.');
        }

        $teacher = $data->getTeacher();
        $course = $data->getCourse();
        if (!$teacher && !$course) {
            throw new BadRequestHttpException('Le professeur ou le cours est requis.');
        }

        if ($teacher && $teacher->getUser() === $user) {
            throw new AccessDeniedHttpException('Vous ne pouvez pas noter votre propre profil.');
        }

        if ($teacher && $this->ratingRepository->findOneBy(['user' => $user, 'teacher' => $teacher])) {
            throw new ConflictHttpException('Vous avez déjà donné votre avis sur ce professeur.');
        }

        if ($course && $this->ratingRepository->findOneBy(['user' => $user, 'course' => $course])) {
            throw new ConflictHttpException('Vous avez déjà donné votre avis sur ce cours.');
        }

        $comment = $data->getComment();
        $data->setComment($comment !== null ? trim($comment) ?: null : null);
        $data->setUser($user);

        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }
}
