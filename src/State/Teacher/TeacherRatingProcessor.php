<?php

namespace App\State\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\RatingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

readonly class TeacherRatingProcessor implements ProcessorInterface
{
    public function __construct(
        private RatingRepository $ratingRepository,
        private EntityManagerInterface $em,
        private Security $security
    )
    {
    }


    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $user = $this->security->getUser();

        if ($data->getTeacher() && $this->ratingRepository->findBy(["user" => $user, "teacher" => $data->getTeacher()]))
            return new JsonResponse([
                'status' => false,
                'message' => "Vous avez déjà donné votre avis sur cet idiomaster"
            ]);

        if ($data->getCourse() && $this->ratingRepository->findBy(["user" => $user, "course" => $data->getCourse()]))
            return new JsonResponse([
                'status' => false,
                'message' => "Vous avez déjà donné votre avis sur ce cours"
            ]);

        $data->setUser($user);

        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }
}
