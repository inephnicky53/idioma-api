<?php

namespace App\Event\Teacher;

use App\Entity\Teacher;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class TeacherStatusChangedEvent extends Event
{
    public function __construct(
        private readonly Teacher $teacher,
        private readonly string $action,
        private readonly ?User $changedBy = null
    ) 
    {
    }

    public function getTeacher(): Teacher
    {
        return $this->teacher;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }
}