<?php


namespace App\Controller\Api\User;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiUserCoursesController extends AbstractController
{
    public function __invoke(): array|JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isGranted('ROLE_STUDENT'))
            return $this->json(['status' => false, "message" => "Vous n'êtes pas étudiant"]);

        return $user->getCourses()->toArray();
    }
}
