<?php


namespace App\Event;

use App\Entity\User;

readonly class NotificationReadEvent
{
    public function __construct(private User $user)
    {
    }

    public function getUser(): User
    {
        return $this->user;
    }
}