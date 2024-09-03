<?php

namespace App\Event;

use App\Entity\User;

class UserPwdResetedEvent
{
    public function __construct(private readonly User $user)
    {
    }

    public function getUser(): User
    {
        return $this->user;
    }
}