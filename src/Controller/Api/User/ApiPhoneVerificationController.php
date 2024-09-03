<?php

namespace App\Controller\Api\User;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ApiPhoneVerificationController extends AbstractController
{

    public function __invoke()
    {
        return $this->getUser();
    }
}
