<?php

namespace App\Controller\Api\Teacher;

use App\Entity\Teacher;
use App\Entity\UserTeacher;
use App\Repository\UserTeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class ApiAddTeacherController extends AbstractController
{

    public function __invoke(Teacher $data, EntityManagerInterface $entityManager)
    {
        $hasTeacher = false;
        $students = $data->getStudents();
        $students->map(function (UserTeacher $userTeacher) use ($data, &$hasTeacher) {
            if ($userTeacher->getTeacher()->getId() === $data->getId()) $hasTeacher = true;
        });
        if ($hasTeacher){
            return $this->json([
                'status' => false,
                "message" => "Ce professeur est déjà ajouté"
            ], Response::HTTP_FORBIDDEN);
        }

        $userTeacher = new UserTeacher();
        $userTeacher
            ->setTeacher($data)
            ->setUser($this->getUser())
        ;

        $entityManager->persist($userTeacher);
        $entityManager->flush();

        return $data;
    }
}
