<?php


namespace App\Event;


use App\Entity\Teacher;

class TeacherValidatedEvent
{
    public function __construct(private readonly Teacher $teacher)
    {
    }

    public function getTeacher(): Teacher
    {
        return $this->teacher;
    }
}
