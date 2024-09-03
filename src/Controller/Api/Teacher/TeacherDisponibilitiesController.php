<?php

namespace App\Controller\Api\Teacher;

use App\Entity\Teacher;
use App\Entity\User;
use App\Entity\UserTeacher;
use App\Repository\UserTeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TeacherDisponibilitiesController extends AbstractController
{

    public function __invoke(Teacher $data, EntityManagerInterface $entityManager)
    {
        /** @var User $user */
        $user = $this->getUser();
        if($user->getTeacher()) {
            $teacher = $user->getTeacher();
            $teacher->t($data->getSpokenLanguages());
            $teacher->setSpokenLanguages($data->getSpokenLanguages());
            $data = $teacher;
        }
        $data->setStep(4);

        dd($data);
        return $data;
    }
}
