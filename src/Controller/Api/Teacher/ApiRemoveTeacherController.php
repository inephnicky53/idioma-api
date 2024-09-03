<?php

namespace App\Controller\Api\Teacher;

use App\Entity\Teacher;
use App\Entity\UserTeacher;
use App\Repository\UserTeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use function Symfony\Component\Translation\t;

class ApiRemoveTeacherController extends AbstractController
{

    public function __invoke(Teacher $data, EntityManagerInterface $entityManager)
    {
        //dd($data);
        $teacher = false;
        $students = $data->getStudents();
        $students->map(function (UserTeacher $userTeacher) use ($data, &$teacher) {
            if ($userTeacher->getTeacher()->getId() === $data->getId()) $teacher = $userTeacher;
        });
        if ($teacher){
            if ($teacher->getHours() > 0) {
                return $this->json([
                    'status' => false,
                    "message" => "Vous avez des heurs disponible pour ce professeur"
                ], Response::HTTP_FORBIDDEN);
            }
        }else{
            return $this->json([
                'status' => false,
                "message" => "Ce professeur ne fait pas partie de vos favoris"
            ], Response::HTTP_FORBIDDEN);
        }

        $entityManager->remove($teacher);
        $entityManager->flush();

        return $this->json([
            'status' => true,
            "message" => "Vous venez de retirer ce professeur"
        ], Response::HTTP_FORBIDDEN);
    }
}
