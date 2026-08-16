<?php

namespace App\Model;

use App\Entity\Course;
use App\Entity\Package;
use App\Entity\Teacher;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback(callback: 'validate')]
class OrderProductModel
{
    public ?Teacher $teacher = null;

    public ?Package $package = null;

    public ?Course $course = null;

    public function validate(ExecutionContextInterface $context): void
    {
        $isTeacherPackage = $this->teacher && $this->package;
        $isCourse = (bool) $this->course;

        if ($isTeacherPackage === $isCourse) {
            $context->buildViolation('Un produit doit contenir soit (teacher + package), soit course, mais pas les deux.')
                ->atPath('course')
                ->addViolation();
        }
    }
}
