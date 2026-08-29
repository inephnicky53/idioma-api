<?php

namespace App\Controller\Api\User;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ApiUserCoursesController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    public function __invoke(): array|JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $rows = $user->getCourses()->toArray();

        $teacher = $user->getTeacher();
        if ($teacher) {
            foreach ($teacher->getCourses() as $course) {
                $rows[] = $course;
            }
        }

        return $rows;
    }
}
