<?php


namespace App\Controller\Api\Teacher;

use App\Entity\Course;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ApiCreateCourseController extends AbstractController
{

    public function __invoke(Course $data)
    {
        $user = $this->getUser();



    }
}
