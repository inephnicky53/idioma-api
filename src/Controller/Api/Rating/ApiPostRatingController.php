<?php


namespace App\Controller\Api\Rating;

use App\Entity\Rating;
use App\Entity\Teacher;
use App\Repository\RatingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ApiPostRatingController extends AbstractController
{
    public function __invoke(Rating $data, RatingRepository $ratingRepository): Rating|\Symfony\Component\HttpFoundation\JsonResponse
    {
        $user = $this->getUser();

        if ($data->getTeacher() && $ratingRepository->findBy(["user" => $user, "teacher" => $data->getTeacher()]))
            return $this->json([
                'status' => false,
                'message' => "Vous avez déjà donné votre avis sur ce professeur"
             ]);

        if ($data->getCourse() && $ratingRepository->findBy(["user" => $user, "course" => $data->getCourse()]))
            return $this->json([
                'status' => false,
                'message' => "Vous avez déjà donné votre avis sur ce cours"
             ]);

        $data->setUser($user);
        return $data;
    }
}
