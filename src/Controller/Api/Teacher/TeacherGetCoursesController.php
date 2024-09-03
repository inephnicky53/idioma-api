<?php

namespace App\Controller\Api\Teacher;

use App\Entity\Teacher;
use App\Entity\UserTeacher;
use App\Repository\UserTeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class TeacherGetCoursesController extends AbstractController
{
    public function __invoke(Teacher $data, EntityManagerInterface $entityManager): array
    {
        return $data->getCourses()->toArray();
    }
}
