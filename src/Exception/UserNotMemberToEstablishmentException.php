<?php


namespace App\Exception;

use App\Entity\Establishment;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class UserNotMemberToEstablishmentException extends AuthenticationException
{
    public function __construct()
    {
        parent::__construct('', 0, null);
    }

    public function getMessageKey(): string
    {
        return "Ce compte ne fait pas parti de l'établissement.";
    }
}
