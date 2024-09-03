<?php


namespace App\Controller\Api\User;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ApiUserMeController extends AbstractController
{
    public function __invoke()
    {
        return $this->getUser();
    }
}
