<?php

namespace App\Model;

use App\Entity\Package;
use App\Entity\Teacher;
use Symfony\Component\Validator\Constraints as Assert;

class OrderProductModel
{
    #[Assert\NotBlank]
    #[Assert\NotNull]
    public ?Teacher $teacher = null;

    #[Assert\NotBlank]
    #[Assert\NotNull]
    public ?Package $package = null;
}